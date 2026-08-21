<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RexamartOrderStatusSyncService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.rexamart.com';

    public function __construct()
    {
        $this->apiKey = config('services.rexamart.api_key', env('REXAMART_API_KEY', ''));
    }

    public function syncOrderStatuses(): void
    {
        if (!(bool) Setting::get('rexamart_order_pusher_enabled', 0)) {
            Log::info('Rexamart order sync skipped: pusher is disabled');
            return;
        }

        $lock = Cache::lock('rexamart_order_sync_lock', 240);

        if (!$lock->get()) {
            Log::info('Rexamart sync skipped due to active lock');
            return;
        }

        try {
            Log::info('Rexamart order sync started');

            Order::whereIn('status', ['pending', 'processing'])
                ->whereNotNull('reference_id')
                ->where('network', 'TELECEL')
                ->where(function ($query) {
                    $query->whereNull('last_synced_at')
                          ->orWhere('last_synced_at', '<', now()->subMinutes(10));
                })
                ->select('id', 'status', 'reference_id', 'network', 'beneficiary_number', 'user_id')
                ->chunkById(50, function ($orders) {
                    foreach ($orders as $index => $order) {
                        try {
                            $this->syncSingleOrder($order);
                        } catch (\Throwable $e) {
                            Log::error('Rexamart order sync failed', [
                                'order_id' => $order->id,
                                'error'    => $e->getMessage(),
                            ]);
                        }

                        if ($index > 0 && $index % 10 === 0) {
                            usleep(200000); // 200ms pause every 10 requests
                        }
                    }
                });

            Log::info('Rexamart order sync completed');
        } finally {
            $lock->release();
        }
    }

    private function syncSingleOrder(Order $order): void
    {
        $url = $this->baseUrl . '/orders/status/' . $order->reference_id;

        $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept'    => 'application/json',
            ])
            ->timeout(15)
            ->get($url);

        if (!$response->successful()) {
            Log::warning('Rexamart status API request failed', [
                'order_id' => $order->id,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            $order->update(['last_synced_at' => now()]);
            return;
        }

        $data = $response->json();

        if (!isset($data['success'], $data['status']) || $data['success'] !== true) {
            Log::warning('Invalid Rexamart status response', [
                'order_id' => $order->id,
                'response' => $data,
            ]);
            $order->update(['last_synced_at' => now()]);
            return;
        }

        $newStatus = $this->mapStatus($data['status']);

        if (!$newStatus || $newStatus === $order->status) {
            $order->update(['last_synced_at' => now()]);
            return;
        }

        $oldStatus = $order->status;

        $order->update([
            'status'         => $newStatus,
            'last_synced_at' => now(),
        ]);

        Log::info('Rexamart order status updated', [
            'order_id'   => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'external'   => $data['status'],
        ]);
    }

    private function mapStatus(string $externalStatus): ?string
    {
        return [
            'completed'  => 'completed',
            'delivered'  => 'completed',
            'processing' => 'processing',
            'pending'    => 'processing',
            'failed'     => 'cancelled',
            'cancelled'  => 'cancelled',
        ][strtolower($externalStatus)] ?? null;
    }
}
