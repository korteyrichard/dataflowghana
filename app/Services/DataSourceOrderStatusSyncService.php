<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DataSourceOrderStatusSyncService
{
    private string $baseUrl;
    private string $apiKey;
    private string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = config('services.datasource.base_url', env('DATASOURCE_BASE_URL'));
        $this->apiKey    = config('services.datasource.api_key', env('DATASOURCE_API_KEY'));
        $this->secretKey = config('services.datasource.secret_key', env('DATASOURCE_SECRET_KEY'));
    }

    /**
     * Main entry point called by cron / command
     */
    public function syncOrderStatuses(): void
    {
        // 🔒 Prevent overlapping cron runs
        $lock = Cache::lock('datasource_order_sync_lock', 240);

        if (! $lock->get()) {
            Log::info('DataSource sync skipped due to active lock');
            return;
        }

        try {
            Log::info('DataSource order sync started');

            Order::whereIn('status', ['pending', 'processing'])
                ->whereNotNull('reference_id')
                ->whereHas('products', function ($query) {
                    $query->where('name', 'like', '%mtn%');
                })
                ->where(function ($query) {
                    $query->whereNull('last_synced_at')
                          ->orWhere('last_synced_at', '<', now()->subMinutes(10));
                })
                ->with('user:id,phone') // ✅ Eager load user to avoid N+1
                ->select('id', 'status', 'reference_id', 'user_id', 'beneficiary_number')
                ->chunkById(50, function ($orders) {

                    foreach ($orders as $index => $order) {
                        try {
                            $this->syncSingleOrder($order);
                        } catch (\Throwable $e) {
                            Log::error('DataSource order sync failed', [
                                'order_id' => $order->id,
                                'error'    => $e->getMessage(),
                            ]);
                        }

                        // ⏱️ Small pause every 10 requests to avoid API rate limiting
                        if ($index > 0 && $index % 10 === 0) {
                            usleep(200000); // 200ms
                        }
                    }

                });

            Log::info('DataSource order sync completed');

        } finally {
            // ✅ Always release the lock, even if an exception occurs
            $lock->release();
        }
    }

    /**
     * Sync a single order with DataSource API
     */
    private function syncSingleOrder(Order $order): void
    {
        $endpoint  = '/api/v1/order/status/single';
        $method    = 'POST';
        $timestamp = time();

        $payload = [
            'orderid' => $order->reference_id,
        ];

        $body            = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signatureString = $timestamp . $method . $endpoint . $body;
        $signature       = hash_hmac('sha256', $signatureString, $this->secretKey);

        $url = rtrim($this->baseUrl, '/') . $endpoint;

        $response = Http::withHeaders([
                'X-API-KEY'    => $this->apiKey,
                'X-Timestamp'  => $timestamp,
                'X-Signature'  => $signature,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(10)
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::warning('DataSource API request failed', [
                'order_id' => $order->id,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            return;
        }

        $data = $response->json();

        if (
            ! isset($data['success'], $data['status']) ||
            $data['success'] !== true
        ) {
            Log::warning('Invalid DataSource response', [
                'order_id' => $order->id,
                'response' => $data,
            ]);
            return;
        }

        $externalStatus = strtolower($data['status']);
        $newStatus      = $this->mapDataSourceStatus($externalStatus);

        if (! $newStatus || $newStatus === $order->status) {
            // Still update sync time to avoid repeated API hits
            $order->update(['last_synced_at' => now()]);
            return;
        }

        $oldStatus = $order->status;

        $order->update([
            'status'         => $newStatus,
            'last_synced_at' => now(),
        ]);

        Log::info('Order status updated from DataSource', [
            'order_id'        => $order->id,
            'old_status'      => $oldStatus,
            'new_status'      => $newStatus,
            'external_status' => $externalStatus,
        ]);
    }

    /**
     * Map DataSource status to local status
     */
    private function mapDataSourceStatus(string $externalStatus): ?string
    {
        return [
            'successful' => 'completed',
            'completed'  => 'completed',
            'delivered'  => 'completed',
            'processing' => 'processing',
            'pending'    => 'processing',
            'pending2'   => 'processing',
            'failed'     => 'cancelled',
            'cancelled'  => 'cancelled',
        ][$externalStatus] ?? null;
    }
}