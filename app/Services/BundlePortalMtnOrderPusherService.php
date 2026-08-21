<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BundlePortalMtnOrderPusherService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.bundleportal.base_url', 'https://api.bundleportal.com/v1');
        $this->apiKey  = config('services.bundleportal.api_key', '');
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
                ? (int) filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT)
                : 0;

            if (empty($phone) || !$gb) {
                Log::warning('BundlePortal MTN: missing data', ['order_id' => $order->id]);
                continue;
            }

            $externalOrderId = 'SD-' . $order->id . '-' . time();
            $payload = [
                'action'       => 'place_order',
                'network'      => 'mtn',
                'recipient'    => $this->formatPhone($phone),
                'package_size' => $gb,
                'order_id'     => $externalOrderId,
            ];

            try {
                $response = Http::timeout(30)
                    ->withHeaders(['x-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                    ->post($this->baseUrl, $payload);

                $data = $response->json();

                Log::info('BundlePortal MTN response', ['order_id' => $order->id, 'status' => $response->status(), 'body' => $data]);

                if ($response->status() === 409 && ($data['error'] ?? '') === 'pending_order') {
                    $order->update(['api_status' => 'failed']);
                    Log::warning('BundlePortal MTN: pending_order conflict', ['order_id' => $order->id]);
                    return;
                }

                if (($data['success'] ?? false) && isset($data['data']['reference'])) {
                    $order->update([
                        'reference_id' => $data['data']['reference'],
                        'api_status'   => 'success',
                    ]);
                    Log::info('BundlePortal MTN order placed', ['reference' => $data['data']['reference']]);
                } else {
                    $message = $data['message'] ?? '';
                    $error   = $data['error'] ?? '';
                    $code    = $data['code'] ?? '';
                    if ($this->isUnverifiedNumberError($message) || $this->isUnverifiedNumberError($error) || $code === 'not_allowlisted') {
                        $this->cancelAndRefund($order, $message ?: $error);
                    } else {
                        $order->update(['api_status' => 'failed']);
                    }
                    Log::error('BundlePortal MTN: order failed', ['order_id' => $order->id, 'message' => $message, 'response' => $data]);
                }
            } catch (\Exception $e) {
                $order->update(['api_status' => 'failed']);
                Log::error('BundlePortal MTN exception', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    public function checkOrderStatus(string $reference): ?array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                ->post($this->baseUrl, ['action' => 'check_status', 'reference' => $reference]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('BundlePortal MTN status check failed', ['reference' => $reference, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function isUnverifiedNumberError(string $message): bool
    {
        $keywords = ['not verified', 'unverified', 'invalid number', 'not registered', 'unregistered', 'number not found', 'not approved', 'not_allowlisted'];
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
            $user = User::lockForUpdate()->find($order->user_id);
            $balanceBefore = $user->wallet_balance;
            $user->increment('wallet_balance', $order->total);
            $balanceAfter = $user->fresh()->wallet_balance;

            $order->update([
                'status'     => 'cancelled',
                'api_status' => 'failed',
            ]);

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

        Log::info('BundlePortal MTN: order auto-cancelled and refunded due to unverified number', [
            'order_id' => $order->id,
            'amount'   => $order->total,
            'reason'   => $reason,
        ]);
    }

    private function isMtn(string $name): bool
    {
        return stripos($name, 'mtn') !== false;
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) === 9 ? '0' . $phone : $phone;
    }
}
