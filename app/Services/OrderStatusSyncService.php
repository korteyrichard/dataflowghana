<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
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
    private $mtnApiKey = '';

    public function syncOrderStatuses()
    {
        $processingOrders = Order::whereIn('status', ['pending', 'processing'])->get();
        
        foreach ($processingOrders as $order) {
            try {
                if (strtolower($order->network) === 'mtn') {
                    $this->syncMtnOrderStatus($order);
                } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime'])) {
                    $this->syncCodeCraftOrderStatus($order);
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync order status', ['orderId' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function syncMtnOrderStatus($order)
    {
        $referenceId = $this->extractReferenceId($order);
        
        Log::info('MTN sync attempt', [
            'order_id' => $order->id,
            'reference_id' => $referenceId,
            'order_network' => $order->network,
            'order_status' => $order->status
        ]);
        
        if (!$referenceId) {
            Log::warning('No reference ID found for MTN order', ['orderId' => $order->id]);
            return;
        }

        try {
            Log::info('Making MTN API call', [
                'order_id' => $order->id,
                'transaction_id' => $referenceId,
                'api_endpoint' => 'https://agent.jaybartservices.com/api/v1/fetch-other-network-transaction'
            ]);
            
            $response = Http::withHeaders([
                'x-api-key' => $this->mtnApiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(20)->post('https://agent.jaybartservices.com/api/v1/fetch-other-network-transaction', [
                'transaction_id' => $referenceId
            ]);

            Log::info('MTN API response received', [
                'order_id' => $order->id,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'is_successful' => $response->successful()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $externalStatus = '';
                if (isset($data['order_items']) && is_array($data['order_items']) && count($data['order_items']) > 0) {
                    $externalStatus = $data['order_items'][0]['status'] ?? '';
                }
                $newStatus = $this->mapMtnStatus($externalStatus);
                
                Log::info('MTN status mapping', [
                    'order_id' => $order->id,
                    'external_status' => $externalStatus,
                    'mapped_status' => $newStatus,
                    'current_order_status' => $order->status
                ]);
                
                if ($newStatus && $newStatus !== $order->status) {
                    $oldStatus = $order->status;
                    $updateResult = $order->update(['status' => $newStatus]);
                    Log::info('MTN order status updated', [
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
                    Log::info('MTN order status unchanged', [
                        'order_id' => $order->id,
                        'current_status' => $order->status,
                        'external_status' => $externalStatus,
                        'mapped_status' => $newStatus
                    ]);
                }
            } else {
                Log::warning('MTN API call unsuccessful', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('MTN status check failed', ['orderId' => $order->id, 'error' => $e->getMessage()]);
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

    private function mapMtnStatus($externalStatus)
    {
        Log::info('MTN Status mapping debug', [
            'input_status' => $externalStatus,
            'input_type' => gettype($externalStatus),
            'input_lowercased' => strtolower($externalStatus)
        ]);
        
        $statusMap = [
            'successful' => 'completed',
            'completed' => 'completed',
            'delivered' => 'completed',
            'processing' => 'processing',
            'pending' => 'processing',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled'
        ];

        $lowercaseStatus = strtolower($externalStatus);
        $mappedStatus = $statusMap[$lowercaseStatus] ?? null;
        
        Log::info('MTN Status mapping result', [
            'original_status' => $externalStatus,
            'lowercase_status' => $lowercaseStatus,
            'mapped_status' => $mappedStatus,
            'available_mappings' => array_keys($statusMap)
        ]);

        return $mappedStatus;
    }
}