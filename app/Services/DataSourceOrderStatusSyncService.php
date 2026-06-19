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

            // Sync individual orders
            $this->syncIndividualOrders();
            
            // Sync bulk orders
            $this->syncBulkOrders();

            Log::info('DataSource order sync completed');

        } finally {
            // ✅ Always release the lock, even if an exception occurs
            $lock->release();
        }
    }
    
    /**
     * Sync individual orders
     */
    private function syncIndividualOrders(): void
    {
        Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->whereHas('products', function ($query) {
                $query->where('name', 'like', '%mtn%');
            })
            // Exclude bulk orders (they have a different reference format or type)
            ->whereDoesntHave('transactions', function ($query) {
                $query->where('type', 'bulk_order');
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
                        Log::error('DataSource individual order sync failed', [
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
    }
    
    /**
     * Sync bulk orders
     */
    private function syncBulkOrders(): void
    {
        // Get bulk orders that need syncing (grouped by reference_id)
        $bulkOrderGroups = Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->whereHas('products', function ($query) {
                $query->where('name', 'like', '%mtn%');
            })
            ->whereHas('transactions', function ($query) {
                $query->where('type', 'bulk_order');
            })
            ->where(function ($query) {
                $query->whereNull('last_synced_at')
                      ->orWhere('last_synced_at', '<', now()->subMinutes(10));
            })
            ->select('id', 'status', 'reference_id', 'beneficiary_number')
            ->get()
            ->groupBy('reference_id');
            
        foreach ($bulkOrderGroups as $referenceId => $orders) {
            try {
                $this->syncBulkOrderGroup($referenceId, $orders);
                
                // Small pause between bulk groups
                usleep(300000); // 300ms
            } catch (\Throwable $e) {
                Log::error('DataSource bulk order sync failed', [
                    'reference_id' => $referenceId,
                    'order_count' => $orders->count(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Sync a bulk order group with DataSource API
     */
    private function syncBulkOrderGroup(string $referenceId, $orders): void
    {
        $endpoint  = '/api/v1/order/bulk/status';
        $method    = 'POST';
        $timestamp = time();

        // Note: Based on the curl example, it uses form data, not JSON
        $formData = [
            'orderid' => $referenceId,
        ];

        // For form data, we need to create the signature differently
        $body = http_build_query($formData);
        $signatureString = $timestamp . $method . $endpoint . $body;
        $signature = hash_hmac('sha256', $signatureString, $this->secretKey);

        $url = rtrim($this->baseUrl, '/') . $endpoint;

        Log::info('DataSource bulk order status sync request', [
            'reference_id' => $referenceId,
            'order_count' => $orders->count(),
            'url' => $url,
            'form_data' => $formData
        ]);

        $response = Http::withHeaders([
                'X-API-KEY'    => $this->apiKey,
                'X-Timestamp'  => $timestamp,
                'X-Signature'  => $signature,
                'Accept'       => 'application/json',
            ])
            ->timeout(15)
            ->asForm()
            ->post($url, $formData);

        if (! $response->successful()) {
            Log::warning('DataSource bulk API request failed', [
                'reference_id' => $referenceId,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            return;
        }

        $data = $response->json();

        Log::info('DataSource bulk status response', [
            'reference_id' => $referenceId,
            'response' => $data
        ]);

        if (! isset($data['success']) || $data['success'] !== true) {
            Log::warning('Invalid DataSource bulk response', [
                'reference_id' => $referenceId,
                'response' => $data,
            ]);
            return;
        }

        // Handle bulk status response - could be overall status or individual statuses
        if (isset($data['status'])) {
            // Single status for all orders in the bulk
            $externalStatus = strtolower($data['status']);
            $newStatus = $this->mapDataSourceStatus($externalStatus);
            
            if ($newStatus) {
                $updatedCount = 0;
                foreach ($orders as $order) {
                    if ($order->status !== $newStatus) {
                        $oldStatus = $order->status;
                        $order->update([
                            'status' => $newStatus,
                            'last_synced_at' => now(),
                        ]);
                        
                        Log::info('Bulk order status updated', [
                            'order_id' => $order->id,
                            'reference_id' => $referenceId,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'external_status' => $externalStatus,
                        ]);
                        
                        $updatedCount++;
                    } else {
                        // Update sync time even if status hasn't changed
                        $order->update(['last_synced_at' => now()]);
                    }
                }
                
                Log::info('Bulk order group sync completed', [
                    'reference_id' => $referenceId,
                    'total_orders' => $orders->count(),
                    'updated_orders' => $updatedCount,
                    'status' => $newStatus
                ]);
            }
        } elseif (isset($data['orders']) && is_array($data['orders'])) {
            // Individual status for each order
            foreach ($data['orders'] as $orderStatus) {
                if (isset($orderStatus['beneficiary_number'], $orderStatus['status'])) {
                    $beneficiary = $orderStatus['beneficiary_number'];
                    $externalStatus = strtolower($orderStatus['status']);
                    $newStatus = $this->mapDataSourceStatus($externalStatus);
                    
                    // Find matching order by beneficiary number
                    $matchingOrder = $orders->firstWhere('beneficiary_number', $beneficiary);
                    
                    if ($matchingOrder && $newStatus && $matchingOrder->status !== $newStatus) {
                        $oldStatus = $matchingOrder->status;
                        $matchingOrder->update([
                            'status' => $newStatus,
                            'last_synced_at' => now(),
                        ]);
                        
                        Log::info('Individual bulk order status updated', [
                            'order_id' => $matchingOrder->id,
                            'reference_id' => $referenceId,
                            'beneficiary' => $beneficiary,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'external_status' => $externalStatus,
                        ]);
                    }
                }
            }
            
            // Update sync time for all orders
            foreach ($orders as $order) {
                $order->update(['last_synced_at' => now()]);
            }
        } else {
            Log::warning('Unexpected bulk status response format', [
                'reference_id' => $referenceId,
                'response' => $data,
            ]);
            
            // Still update sync time to avoid repeated failed attempts
            foreach ($orders as $order) {
                $order->update(['last_synced_at' => now()]);
            }
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