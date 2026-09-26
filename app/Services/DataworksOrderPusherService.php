<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataworksOrderPusherService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.dataworks.base_url', 'https://dataworksgh.com/api/v1');
        $this->apiKey  = config('services.dataworks.api_key', '');
    }

    public function pushOrderToApi(Order $order): void
    {
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();

        foreach ($items as $item) {
            if (!$this->isMtn($item->name)) {
                continue;
            }

            $phone   = $item->pivot->beneficiary_number;
            $variant = ProductVariant::find($item->pivot->product_variant_id);
            $gb      = $variant && isset($variant->variant_attributes['size'])
                ? (float) filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
                : 0;

            if (empty($phone) || !$gb) {
                Log::warning('Dataworks: missing phone or bundle size', ['order_id' => $order->id]);
                continue;
            }

            $payload = [
                'recipient_number' => $this->formatPhone($phone),
                'gig'              => (string) $gb,
                'webhook_url'      => url('/api/dataworks/webhook'),
            ];

            try {
                $response = Http::timeout(30)
                    ->withHeaders(['x-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                    ->post("{$this->baseUrl}/regular.php", $payload);

                $data = $response->json();

                Log::info('Dataworks API response', ['order_id' => $order->id, 'status_code' => $response->status(), 'response' => $data]);

                if (($data['code'] ?? null) === 200 && ($data['status'] ?? '') === 'success') {
                    $referenceId = $data['data']['order']['reference_id'] ?? $data['data']['order']['order_id'] ?? null;
                    $order->update([
                        'reference_id' => $referenceId,
                        'api_status'   => 'success',
                    ]);
                    Log::info('Dataworks order pushed successfully', ['order_id' => $order->id, 'reference_id' => $referenceId]);
                } else {
                    $message = $data['message'] ?? '';
                    if ($this->isUnverifiedNumberError($message)) {
                        $this->cancelAndRefund($order, $message);
                    } else {
                        $order->update(['api_status' => 'failed']);
                    }
                    Log::error('Dataworks order failed', ['order_id' => $order->id, 'response' => $data]);
                }
            } catch (\Exception $e) {
                $order->update(['api_status' => 'failed']);
                Log::error('Dataworks exception', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    public function checkOrderStatus(string $referenceId): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/status.php", ['reference_ids' => [$referenceId]]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Dataworks status check failed', ['reference_id' => $referenceId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function isMtn(string $name): bool
    {
        return stripos($name, 'mtn') !== false;
    }

    private function isUnverifiedNumberError(string $message): bool
    {
        $keywords = ['not verified', 'unverified', 'invalid number', 'not registered', 'unregistered', 'number not found', 'not approved'];
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function cancelAndRefund(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $user          = User::lockForUpdate()->find($order->user_id);
            $balanceBefore = $user->wallet_balance;
            $user->increment('wallet_balance', $order->total);
            $balanceAfter  = $user->fresh()->wallet_balance;

            $order->update(['status' => 'cancelled', 'api_status' => 'failed']);

            Transaction::create([
                'user_id'        => $order->user_id,
                'order_id'       => $order->id,
                'amount'         => $order->total,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'status'         => 'completed',
                'type'           => 'refund',
                'description'    => "Auto-refund for order #{$order->id}: {$reason}",
            ]);
        });

        Log::info('Dataworks: order auto-cancelled and refunded', [
            'order_id' => $order->id,
            'amount'   => $order->total,
            'reason'   => $reason,
        ]);
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) === 9 ? '0' . $phone : $phone;
    }
}
