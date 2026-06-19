<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use App\Services\DataMasterOrderStatusSyncService;
use App\Services\DataEasyStatusSyncService;
use App\Services\DataSourceOrderStatusSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderStatusSyncService
{
    private $smsService;

    public function __construct(MoolreSmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    private $codeCraftAgentEmail = '';

    public function syncOrderStatuses()
    {
        $processingOrders = Order::whereIn('status', ['pending', 'processing'])->get();
        
        // Sync DataMaster orders first
        $dataMasterSync = new DataMasterOrderStatusSyncService($this->smsService);
        $dataMasterSync->syncOrderStatuses();
        
        // Sync DataEasy orders
        $dataEasySync = new DataEasyStatusSyncService($this->smsService);
        $dataEasySync->syncOrderStatuses();
        
        // Sync DataSource orders (Order Pusher)
        $dataSourceSync = new DataSourceOrderStatusSyncService();
        $dataSourceSync->syncOrderStatuses();
        
        foreach ($processingOrders as $order) {
            try {
                // Skip MTN Express orders as they're handled by DataMaster sync
                $isMtnExpress = $order->products->contains(function($product) {
                    return stripos($product->name, 'mtn express') !== false;
                });
                
                if ($isMtnExpress) {
                    continue;
                }
                
                // Skip DataSource orders as they're handled by DataSource sync
                $isDataSource = $order->products->contains(function($product) {
                    return stripos($product->name, 'mtn') !== false && stripos($product->name, 'mtn express') === false;
                });
                
                if ($isDataSource) {
                    continue;
                }
                
                // Skip DataEasy orders as they're handled by DataEasy sync above
                $isDataEasy = strlen($order->reference_id) > 20 && !is_numeric($order->reference_id);
                if ($isDataEasy) {
                    continue;
                }
                
                if (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime'])) {
                    $this->syncCodeCraftOrderStatus($order);
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function syncCodeCraftOrderStatus($order)
    {
        $referenceId = $this->extractReferenceId($order);
        
        Log::info('CodeCraft sync attempt', [
            'order_id' => $order->id,
            'reference_id' => $referenceId,
            'order_network' => $order->network,
            'order_status' => $order->status
        ]);
        
        if (!$referenceId) {
            Log::warning('No reference ID found for CodeCraft order', ['order_id' => $order->id]);
            return;
        }

        $endpoint = 'https://api.codecraftnetwork.com/api/response_agent.php';
        
        try {
            Log::info('Making CodeCraft API call', [
                'order_id' => $order->id,
                'reference_id' => $referenceId,
                'api_endpoint' => $endpoint
            ]);
            
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(20)->get($endpoint, [
                'client_email' => $this->codeCraftAgentEmail,
                'reference_id' => $referenceId
            ]);

            Log::info('CodeCraft API response received', [
                'order_id' => $order->id,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'is_successful' => $response->successful()
            ]);

            if ($response->successful()) {
                $body = $response->body();
                
                // Check for PHP errors or HTML content
                if (str_contains($body, 'Fatal error') || str_contains($body, '<br />')) {
                    Log::error('CodeCraft API Database Error', [
                        'order_id' => $order->id,
                        'client_email' => $this->codeCraftAgentEmail,
                        'reference_id' => $referenceId,
                        'error_type' => 'Database Connection',
                        'api_endpoint' => $endpoint,
                        'response_body' => $body,
                        'support_message' => 'Please contact CodeCraft support and provide them this error log. Their database query is failing.'
                    ]);
                    return;
                }
                
                try {
                    $data = $response->json();
                    if (!isset($data['order_status'])) {
                        Log::error('CodeCraft API response missing order_status', [
                            'order_id' => $order->id,
                            'response_data' => $data
                        ]);
                        return;
                    }
                    $externalStatus = $data['order_status'];
                    $newStatus = $this->mapCodeCraftStatus($externalStatus);
                } catch (\Exception $e) {
                    Log::error('Failed to parse CodeCraft API response', [
                        'order_id' => $order->id,
                        'response_body' => $body,
                        'error' => $e->getMessage()
                    ]);
                    return;
                }
                
                Log::info('CodeCraft status mapping', [
                    'order_id' => $order->id,
                    'external_status' => $externalStatus,
                    'mapped_status' => $newStatus,
                    'current_order_status' => $order->status
                ]);
                
                if ($newStatus && $newStatus !== $order->status) {
                    $oldStatus = $order->status;
                    $updateResult = $order->update(['status' => $newStatus]);
                    Log::info('CodeCraft order status updated', [
                        'orderId' => $order->id, 
                        'oldStatus' => $oldStatus, 
                        'newStatus' => $newStatus,
                        'update_successful' => $updateResult
                    ]);
                    
                    if ($newStatus === 'completed' && $order->phone) {
                        $message = "Your order #{$order->id} for {$order->beneficiary_number} has been completed successfully. Thank you for using our service!";
                        $this->smsService->sendSms($order->phone, $message);
                    }
                } else {
                    Log::info('CodeCraft order status unchanged', [
                        'order_id' => $order->id,
                        'current_status' => $order->status,
                        'external_status' => $externalStatus,
                        'mapped_status' => $newStatus
                    ]);
                }
            } else {
                Log::warning('CodeCraft API call unsuccessful', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('CodeCraft status check failed', [
                'orderId' => $order->id, 
                'error' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function extractReferenceId($order)
    {
        return $order->reference_id;
    }

    private function mapCodeCraftStatus($externalStatus)
    {
        $statusMap = [
            'Crediting successful' => 'completed',
            'completed' => 'completed',
            'delivered' => 'completed',
            'processing' => 'processing',
            'placed' => 'processing',
            'cancelled' => 'cancelled',
            'failed' => 'cancelled'
        ];

        return $statusMap[strtolower($externalStatus)] ?? null;
    }
}
