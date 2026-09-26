<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BundlePortalMtnOrderPusherService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.bundleportal.base_url', 'https://api.bundleportal.com/v2');
        $this->apiKey  = config('services.bundleportal.api_key', '');
    }

    public function pushOrderToApi(Order $order): void
    {
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();

        foreach ($items as $item) {
            if (!$this->isMtn($item->name)) {
                continue;
            }

            $phone = $item->pivot->beneficiary_number;
            $variant = ProductVariant::find($item->pivot->product_variant_id);
            $gb = $variant && isset($variant->variant_attributes['size'])
                ? (int) filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT)
                : 0;

            if (empty($phone) || !$gb) {
                Log::warning('BundlePortal MTN: missing data', ['order_id' => $order->id]);
                continue;
            }

            $recipient = $this->formatPhone($phone);
            $externalOrderId = 'SD-' . $order->id;
            $isRetry = $order->reference_id === $externalOrderId;
            $payload = [
                'action' => 'place_order',
                'network' => 'mtn',
                'recipient' => $recipient,
                'package_size' => $gb,
                'order_id' => $externalOrderId,
            ];
            $order->update(['reference_id' => $externalOrderId]);

            try {
                if (!$isRetry) {
                    $verification = Http::timeout(30)
                        ->withHeaders(['x-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                        ->post($this->baseUrl, [
                            'action' => 'verify_number',
                            'network' => 'mtn',
                            'recipient' => $recipient,
                        ]);
                    $verificationData = $verification->json() ?? [];

                    if (($verificationData['success'] ?? false)
                        && array_key_exists('can_order', $verificationData['data'] ?? [])
                        && !$verificationData['data']['can_order']) {
                        BundlePortalOrderFailureService::refund(
                            $order,
                            $verificationData['data']['allowlist_message'] ?? 'Recipient is not approved or has a previous MTN order still in flight.'
                        );
                        return;
                    }

                    if (!($verificationData['success'] ?? false) || !($verificationData['data']['can_order'] ?? false)) {
                        BundlePortalOrderFailureService::refund(
                            $order,
                            $verificationData['message'] ?? $verificationData['error'] ?? 'Bundle Portal MTN number verification failed.'
                        );
                        Log::warning('BundlePortal MTN number verification failed', [
                            'order_id' => $order->id,
                            'status' => $verification->status(),
                            'response' => $verificationData,
                        ]);
                        return;
                    }
                }

                $response = Http::timeout(30)
                    ->withHeaders(['x-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                    ->post($this->baseUrl, $payload);

                $data = $response->json();

                Log::info('BundlePortal MTN response', ['order_id' => $order->id, 'status' => $response->status(), 'body' => $data]);

                if ($response->status() === 409 && (($data['code'] ?? $data['error'] ?? '') === 'pending_order')) {
                    BundlePortalOrderFailureService::refund($order, $data['message'] ?? 'A previous order is still being delivered.');
                    Log::warning('BundlePortal MTN: pending_order conflict', ['order_id' => $order->id]);
                    return;
                }

                if (($data['success'] ?? false) && isset($data['data']['reference'])) {
                    $order->update([
                        'reference_id' => $externalOrderId,
                        'api_status'   => 'success',
                    ]);
                    Log::info('BundlePortal MTN order placed', ['order_id' => $externalOrderId, 'reference' => $data['data']['reference']]);
                } else {
                    $retryable = in_array($response->status(), [429, 500], true)
                        || ($response->status() === 503 && ($data['code'] ?? '') === 'order_capacity_busy');
                    if ($retryable) {
                        $order->update(['reference_id' => $externalOrderId]);
                    } else {
                        BundlePortalOrderFailureService::refund($order, $data['message'] ?? $data['error'] ?? 'Bundle Portal rejected the order.');
                    }
                    Log::error('BundlePortal MTN: order failed', ['order_id' => $order->id, 'response' => $data]);
                }
            } catch (\Exception $e) {
                $order->update(['reference_id' => $externalOrderId]);
                Log::error('BundlePortal MTN exception', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
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
