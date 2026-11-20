<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\User;

class MaxDataOrderPusherService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.maxdata.base_url'), '/');
        $this->apiKey = config('services.maxdata.api_key', '');
    }

    public function pushOrderToApi(Order $order): bool
    {
        if ($this->apiKey === '') {
            Log::error('Max Data API key is not configured', ['order_id' => $order->id]);
            $order->update(['api_status' => 'failed']);
            return false;
        }

        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        $item = $items->first(fn ($product) => stripos($product->name, 'mtn express') !== false);

        if (!$item) {
            Log::warning('Max Data skipped a non MTN Express order', ['order_id' => $order->id]);
            return false;
        }

        $phone = preg_replace('/\D+/', '', (string) $item->pivot->beneficiary_number);
        $variant = ProductVariant::find($item->pivot->product_variant_id);
        $sizeMb = $this->getSizeMb($variant);

        if (!preg_match('/^0\d{9}$/', $phone) || !$sizeMb) {
            Log::warning('Max Data order is missing a valid recipient or bundle size', ['order_id' => $order->id]);
            $order->update(['api_status' => 'failed']);
            return false;
        }

        try {
            $bundleId = $this->findBundleId($sizeMb);
            if (!$bundleId) {
                Log::error('Max Data package could not be matched to MTN Express bundle size', [
                    'order_id' => $order->id,
                    'size_mb' => $sizeMb,
                ]);
                $order->update(['api_status' => 'failed']);
                return false;
            }

            $reference = 'DF-' . $order->id;
            $response = $this->request()->post("{$this->baseUrl}/purchases", [
                'reference' => $reference,
                'bundle_id' => $bundleId,
                'recipient' => $phone,
            ]);
            $data = $response->json();
            $purchase = $data['data'] ?? [];

            if ($response->successful() && ($data['success'] ?? false) && isset($purchase['id'])) {
                $order->update([
                    'reference_id' => 'MAXDATA:' . $purchase['id'],
                    'api_status' => 'success',
                ]);
                Log::info('Max Data purchase accepted', [
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'purchase_id' => $purchase['id'],
                    'purchase_status' => $purchase['status'] ?? null,
                ]);
                return true;
            }

            $errorText = $this->responseErrorText($data, $response->body());
            $isUnverifiedNumber = strtolower((string) ($data['error'] ?? '')) === 'number_not_verified'
                || $this->isUnverifiedNumberError($errorText);

            if ($isUnverifiedNumber) {
                $this->cancelAndRefund($order, $errorText);
            } else {
                $order->update(['api_status' => 'failed']);
            }

            Log::error('Max Data purchase rejected', [
                'order_id' => $order->id,
                'status_code' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'response_body' => mb_substr(str_replace($phone, '[redacted recipient]', $response->body()), 0, 1500),
                'number_unverified' => $isUnverifiedNumber,
            ]);
            return false;
        } catch (\Throwable $e) {
            $order->update(['api_status' => 'failed']);
            Log::error('Max Data purchase failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function checkPurchase(string $purchaseId): ?array
    {
        try {
            $response = $this->request()->get("{$this->baseUrl}/purchases/" . rawurlencode($purchaseId));
            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error('Max Data status check failed', ['purchase_id' => $purchaseId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function request()
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->apiKey)
            ->timeout(30);
    }

    private function findBundleId(int $sizeMb): ?int
    {
        $packages = Cache::remember('maxdata.mtn.packages', now()->addMinutes(10), function () {
            $response = $this->request()->get("{$this->baseUrl}/packages");
            if (!$response->successful()) {
                throw new \RuntimeException('Max Data packages endpoint returned HTTP ' . $response->status());
            }

            $body = $response->json();
            $packages = $body['data']['packages'] ?? $body['data'] ?? [];
            return is_array($packages) ? $packages : [];
        });

        foreach ($packages as $package) {
            if (!is_array($package) || !isset($package['id'])) {
                continue;
            }

            $network = strtolower((string) ($package['network'] ?? 'mtn'));
            if (!str_contains($network, 'mtn')) {
                continue;
            }

            $packageSize = isset($package['size_mb']) ? (int) $package['size_mb'] : $this->parseSizeMb(
                (string) ($package['bundle'] ?? $package['name'] ?? $package['size'] ?? '')
            );
            if ($packageSize === $sizeMb) {
                return (int) $package['id'];
            }
        }

        return null;
    }

    private function getSizeMb(?ProductVariant $variant): ?int
    {
        if (!$variant) {
            return null;
        }

        $attributes = $variant->variant_attributes ?? [];
        return $this->parseSizeMb((string) ($attributes['size'] ?? '')) ?: null;
    }

    private function parseSizeMb(string $size): int
    {
        if (!preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB)?/i', $size, $matches)) {
            return 0;
        }

        $amount = (float) $matches[1];
        return strtoupper($matches[2] ?? 'GB') === 'MB'
            ? (int) round($amount)
            : (int) round($amount * 1024);
    }

    private function responseErrorText(?array $data, string $body): string
    {
        $text = [];
        $collect = function ($value) use (&$collect, &$text): void {
            if (is_string($value)) {
                $text[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $nested) {
                    $collect($nested);
                }
            }
        };

        if ($data !== null) {
            $collect($data);
        }
        $text[] = $body;

        return implode(' ', $text);
    }

    private function isUnverifiedNumberError(string $message): bool
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $message);
        $mentionsRecipient = preg_match('/\b(number|recipient|phone)\b/i', $normalized) === 1;
        $saysNotVerified = preg_match('/\bnot\s+(?:been\s+)?verified\b|\bunverified\b/i', $normalized) === 1;

        return $mentionsRecipient && $saysNotVerified;
    }

    private function cancelAndRefund(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason): void {
            $lockedOrder = Order::lockForUpdate()->find($order->id);
            if (!$lockedOrder || in_array($lockedOrder->status, ['cancelled', 'completed'], true)) {
                return;
            }

            $user = User::lockForUpdate()->find($lockedOrder->user_id);
            if (!$user) {
                Log::error('Max Data could not refund order because its user was not found', [
                    'order_id' => $lockedOrder->id,
                    'user_id' => $lockedOrder->user_id,
                ]);
                return;
            }

            $balanceBefore = $user->wallet_balance;
            $user->increment('wallet_balance', $lockedOrder->total);
            $balanceAfter = $user->fresh()->wallet_balance;

            $lockedOrder->update([
                'status' => 'cancelled',
                'api_status' => 'failed',
            ]);

            Transaction::create([
                'user_id' => $lockedOrder->user_id,
                'order_id' => $lockedOrder->id,
                'amount' => $lockedOrder->total,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'type' => 'refund',
                'description' => "Auto-refund for Max Data order #{$lockedOrder->id}: {$reason}",
            ]);

            Log::info('Max Data order auto-cancelled and refunded', [
                'order_id' => $lockedOrder->id,
                'amount' => $lockedOrder->total,
            ]);
        });
    }
}
