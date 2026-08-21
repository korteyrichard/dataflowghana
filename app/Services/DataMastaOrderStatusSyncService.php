<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataMastaOrderStatusSyncService
{
    private string $baseUrl = 'https://www.datamastergh.shop';
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.datamasta.api_key', '');
    }

    public function syncOrderStatuses(): void
    {
        if (!(bool) Setting::get('datamasta_order_pusher_enabled', 0)) {
            Log::info('DataMasta order sync skipped: pusher is disabled');
            return;
        }

        if (empty($this->apiKey)) {
            Log::error('DataMasta API key not configured, skipping sync');
            return;
        }

        Log::info('DataMasta order sync started');

        Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->where('reference_id', 'like', 'DF-%')
            ->where('api_status', 'success')
            ->whereHas('products', fn($q) => $q->where('name', 'like', '%mtn%'))
            ->select('id', 'status', 'reference_id', 'user_id', 'beneficiary_number')
            ->with('user:id,phone')
            ->chunkById(50, function ($orders) {
                Log::info('DataMasta sync: processing chunk', ['count' => $orders->count()]);
                foreach ($orders as $order) {
                    try {
                        Log::info('DataMasta syncing order', [
                            'order_id'   => $order->id,
                            'reference'  => $order->reference_id,
                            'status'     => $order->status,
                        ]);
                        $this->syncOrder($order);
                    } catch (\Exception $e) {
                        Log::error('DataMasta status sync failed', [
                            'order_id' => $order->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('DataMasta order sync completed');
    }

    private function syncOrder(Order $order): void
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Accept'    => 'application/json',
        ])->timeout(15)->get($this->baseUrl . '/api/v1/order-status/' . $order->reference_id);

        if (!$response->successful()) {
            Log::warning('DataMasta status check failed', [
                'order_id'    => $order->id,
                'reference'   => $order->reference_id,
                'status_code' => $response->status(),
            ]);
            return;
        }

        $data = $response->json();
        $externalStatus = strtolower($data['status'] ?? '');
        $newStatus = $this->mapStatus($externalStatus);

        Log::info('DataMasta API response', [
            'order_id'        => $order->id,
            'reference'       => $order->reference_id,
            'external_status' => $externalStatus,
            'mapped_status'   => $newStatus,
            'current_status'  => $order->status,
        ]);

        if (!$newStatus || $newStatus === $order->status) {
            return;
        }

        $oldStatus = $order->status;
        $order->update(['status' => $newStatus]);

        Log::info('DataMasta order status updated', [
            'order_id'        => $order->id,
            'old_status'      => $oldStatus,
            'new_status'      => $newStatus,
            'external_status' => $externalStatus,
        ]);

        if ($newStatus === 'completed' && $order->user?->phone) {
            try {
                (new MoolreSmsService())->sendSms(
                    $order->user->phone,
                    "Your MTN data order #{$order->id} for {$order->beneficiary_number} has been completed. Thank you!"
                );
            } catch (\Exception $e) {
                Log::error('DataMasta SMS failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function mapStatus(string $status): ?string
    {
        return [
            'completed'  => 'completed',
            'successful' => 'completed',
            'processing' => 'processing',
            'pending'    => 'pending',
        ][$status] ?? null;
    }
}
