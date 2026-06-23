<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CodeCraftMtnOrderStatusSyncService
{
    private $apiKey;
    private $baseUrl = 'https://api.codecraftnetwork.com/api';
    private $smsService;

    public function __construct()
    {
        $this->apiKey = config('services.codecraft_mtn.api_key', '');
        $this->smsService = new MoolreSmsService();
    }

    public function syncOrderStatuses()
    {
        $enabled = (bool) Setting::get('codecraft_mtn_order_pusher_enabled', 0);

        if (!$enabled || empty($this->apiKey)) {
            return;
        }

        $orders = Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->where('reference_id', 'like', 'API%')
            ->where('network', 'like', '%MTN%')
            ->with('user', 'products')
            ->get();

        foreach ($orders as $order) {
            try {
                $this->syncOrder($order);
            } catch (\Exception $e) {
                Log::error('Failed to sync CodeCraft MTN order status', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function syncOrder($order)
    {
        $referenceId = $order->reference_id;

        if (!$referenceId) return;

        $isBigTime = false;
        if ($order->products->isNotEmpty()) {
            $isBigTime = stripos($order->products->first()->name, 'big') !== false;
        }

        $endpoint = $isBigTime
            ? $this->baseUrl . '/response_big_time.php'
            : $this->baseUrl . '/response_regular.php';

        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->get($endpoint, ['reference_id' => $referenceId]);

            Log::info('CodeCraft MTN status check', [
                'order_id' => $order->id,
                'reference_id' => $referenceId,
                'status_code' => $response->status(),
                'response' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] && isset($data['data']['order_status'])) {
                    $externalStatus = $data['data']['order_status'];
                    $newStatus = $this->mapStatus($externalStatus);

                    if ($newStatus && $newStatus !== $order->status) {
                        $oldStatus = $order->status;
                        $order->update(['status' => $newStatus]);

                        Log::info('CodeCraft MTN order status updated', [
                            'order_id' => $order->id,
                            'old' => $oldStatus,
                            'new' => $newStatus
                        ]);

                        if ($newStatus === 'completed' && $order->user && $order->user->phone) {
                            try {
                                $message = "Your order #{$order->id} for {$order->network} data has been completed successfully. Thank you!";
                                $this->smsService->sendSms($order->user->phone, $message);
                            } catch (\Exception $e) {
                                Log::error('Failed to send SMS', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('CodeCraft MTN status check failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function mapStatus($externalStatus)
    {
        $map = [
            'completed' => 'completed',
            'successful' => 'completed',
            'delivered' => 'completed',
            'crediting successful' => 'completed',
            'pending' => 'processing',
            'processing' => 'processing',
            'failed' => 'cancelled',
            'cancelled' => 'cancelled'
        ];

        return $map[strtolower($externalStatus)] ?? null;
    }
}
