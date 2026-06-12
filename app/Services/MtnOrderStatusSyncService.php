<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MtnOrderStatusSyncService
{
    private $baseUrl;
    private $apiKey;
    private $smsService;

    public function __construct(MoolreSmsService $smsService)
    {
        $this->baseUrl = config('services.order_pusher.base_url', 'https://agent.jaybartservices.com/api/v1');
        $this->apiKey = config('services.order_pusher.api_key');
        $this->smsService = $smsService;
    }

    public function syncOrderStatuses()
    {
        $processingOrders = Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->whereHas('products', function($query) {
                $query->where('name', 'like', '%mtn%')->where('name', 'not like', '%mtn express%');
            })
            ->get();

        foreach ($processingOrders as $order) {
            try {
                $this->syncMtnOrderStatus($order);
            } catch (\Exception $e) {
                Log::error('Failed to sync MTN order status', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function syncMtnOrderStatus($order)
    {
        $referenceId = $order->reference_id;

        if (!$referenceId) {
            Log::warning('No reference ID found for MTN order', ['order_id' => $order->id]);
            return;
        }

        try {
            $endpoint = $this->baseUrl . '/order/bulk/status';

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(30)->post($endpoint, [
                'orderid' => $referenceId,
                'data_size' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] === true && isset($data['recored']) && is_array($data['recored'])) {
                    $orderData = $data['recored'][0];
                    $externalStatus = $orderData['status'] ?? '';

                    $newStatus = $this->mapMtnStatus($externalStatus);

                    if ($newStatus && $newStatus !== $order->status) {
                        $oldStatus = $order->status;
                        $order->update(['status' => $newStatus]);

                        Log::info('MTN order status updated', [
                            'order_id' => $order->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'external_status' => $externalStatus
                        ]);

                        if ($newStatus === 'completed' && $order->user && $order->user->phone) {
                            $message = "Your MTN order #{$order->id} for {$order->beneficiary_number} has been completed successfully. Thank you!";
                            $this->smsService->sendSms($order->user->phone, $message);
                        }
                    }
                } else {
                    Log::warning('MTN Order Pusher API response invalid', [
                        'order_id' => $order->id,
                        'response' => $data
                    ]);
                }
            } else {
                Log::warning('MTN Order Pusher status sync failed', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('MTN status sync exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function mapMtnStatus($externalStatus)
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
