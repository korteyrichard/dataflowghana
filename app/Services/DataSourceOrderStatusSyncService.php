<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataSourceOrderStatusSyncService
{
    private $baseUrl;
    private $apiKey;
    private $smsService;

    public function __construct(MoolreSmsService $smsService)
    {
        $this->baseUrl = config('services.datasource.base_url', env('DATASOURCE_BASE_URL'));
        $this->apiKey = config('services.datasource.api_key', env('DATASOURCE_API_KEY'));
        $this->smsService = $smsService;
    }

    public function syncOrderStatuses()
    {
        $processingOrders = Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->whereHas('products', function($query) {
                $query->where('name', 'like', '%mtn%');
            })
            ->get();

        $allDataSourceOrders = Order::whereNotNull('reference_id')
            ->whereHas('products', function($query) {
                $query->where('name', 'like', '%mtn%');
            })
            ->get();

        Log::info('DataSource orders to sync', [
            'count' => $processingOrders->count(),
            'total_with_reference' => $allDataSourceOrders->count(),
            'statuses' => $allDataSourceOrders->pluck('status')->unique()
        ]);

        foreach ($processingOrders as $order) {
            try {
                $this->syncDataSourceOrderStatus($order);
            } catch (\Exception $e) {
                Log::error('Failed to sync DataSource order status', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function syncDataSourceOrderStatus($order)
    {
        $referenceId = $order->reference_id;

        if (!$referenceId) {
            Log::warning('No reference ID found for DataSource order', ['order_id' => $order->id]);
            return;
        }

        Log::info('DataSource order found for status sync', [
            'order_id' => $order->id,
            'current_status' => $order->status,
            'reference_id' => $referenceId
        ]);

        try {
            $endpoint = '/api/v1/order/bulk/status';
            $timestamp = time();
            $method = 'POST';
            
            $bodyArray = [
                'orderid' => $referenceId,
                'data_size' => 1
            ];
            $body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);
            
            $signatureString = $timestamp . $method . $endpoint . $body;
            $signature = hash_hmac('sha256', $signatureString, config('services.datasource.secret_key', env('DATASOURCE_SECRET_KEY')));

            $fullUrl = $this->baseUrl . $endpoint;

            Log::info('DataSource Order Status Sync - Request details', [
                'order_id' => $order->id,
                'base_url' => $this->baseUrl,
                'endpoint' => $endpoint,
                'full_url' => $fullUrl,
                'reference_id' => $referenceId,
                'request_body' => $bodyArray
            ]);

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(30)->post($fullUrl, $bodyArray);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('DataSource Order Status Sync - Response received', [
                    'order_id' => $order->id,
                    'response' => $data
                ]);

                if (isset($data['success']) && $data['success'] === true && isset($data['recored']) && is_array($data['recored'])) {
                    $orderData = $data['recored'][0];
                    $externalStatus = $orderData['status'] ?? '';

                    $newStatus = $this->mapDataSourceStatus($externalStatus);

                    if ($newStatus && $newStatus !== $order->status) {
                        $oldStatus = $order->status;
                        $order->update(['status' => $newStatus]);

                        Log::info('DataSource order status updated', [
                            'order_id' => $order->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'external_status' => $externalStatus
                        ]);

                        if ($newStatus === 'completed' && $order->user && $order->user->phone) {
                            $message = "Your order #{$order->id} for {$order->beneficiary_number} has been completed successfully. Thank you!";
                            $this->smsService->sendSms($order->user->phone, $message);
                        }
                    }
                } else {
                    Log::warning('DataSource Order Pusher API response invalid', [
                        'order_id' => $order->id,
                        'response' => $data
                    ]);
                }
            } else {
                Log::warning('DataSource Order Pusher status sync failed', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('DataSource status sync exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function mapDataSourceStatus($externalStatus)
    {
        $statusMap = [
            'successful' => 'completed',
            'completed' => 'completed',
            'delivered' => 'completed',
            'processing' => 'processing',
            'pending' => 'processing',
            'pending2' => 'processing',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled'
        ];

        $lowercaseStatus = strtolower($externalStatus);
        return $statusMap[$lowercaseStatus] ?? null;
    }
}
