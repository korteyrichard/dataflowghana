<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BundlePortalOrderPusherService
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
            $network = $this->getNetwork($item->name);

            if (!$network) {
                continue;
            }

            $phone   = $item->pivot->beneficiary_number;
            $variant = ProductVariant::find($item->pivot->product_variant_id);
            $gb      = $variant && isset($variant->variant_attributes['size'])
                ? (int) filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT)
                : 0;

            if (empty($phone) || !$gb) {
                Log::warning('BundlePortal: missing data', ['order_id' => $order->id]);
                continue;
            }

            $recipient = $this->formatPhone($phone);
            $externalOrderId = 'SD-' . $order->id;
            $payload = [
                'action'       => 'place_order',
                'network'      => $network,
                'recipient'    => $recipient,
                'package_size' => $gb,
                'order_id'     => $externalOrderId,
            ];
            $isRetry = $order->reference_id === $externalOrderId;
            $order->update(['reference_id' => $externalOrderId]);

            try {
                if (!$isRetry) {
                    $verification = Http::timeout(30)
                        ->withHeaders(['x-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                        ->post($this->baseUrl, [
                            'action' => 'verify_number',
                            'network' => $network,
                            'recipient' => $recipient,
                        ]);
                    $verificationData = $verification->json() ?? [];

                    if (($verificationData['success'] ?? false) && array_key_exists('can_order', $verificationData['data'] ?? []) && !$verificationData['data']['can_order']) {
                        BundlePortalOrderFailureService::refund(
                            $order,
                            $verificationData['data']['allowlist_message'] ?? 'Recipient is not approved or has a previous order still in flight.'
                        );
                        return;
                    }

                    if (!($verificationData['success'] ?? false) || !($verificationData['data']['can_order'] ?? false)) {
                        BundlePortalOrderFailureService::refund(
                            $order,
                            $verificationData['message'] ?? $verificationData['error'] ?? 'Bundle Portal number verification failed.'
                        );
                        Log::warning('BundlePortal number verification failed', [
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

                Log::info('BundlePortal response', ['order_id' => $order->id, 'status' => $response->status(), 'body' => $data]);

                if ($response->status() === 409 && (($data['code'] ?? $data['error'] ?? '') === 'pending_order')) {
                    BundlePortalOrderFailureService::refund($order, $data['message'] ?? 'A previous order is still being delivered.');
                    Log::warning('BundlePortal: pending_order conflict', ['order_id' => $order->id]);
                    return;
                }

                if (($data['success'] ?? false) && isset($data['data']['reference'])) {
                    $order->update([
                        'reference_id' => $externalOrderId,
                        'api_status'   => 'success',
                    ]);
                    Log::info('BundlePortal order placed', ['order_id' => $externalOrderId, 'reference' => $data['data']['reference']]);
                } else {
                    $retryable = in_array($response->status(), [429, 500], true)
                        || ($response->status() === 503 && ($data['code'] ?? '') === 'order_capacity_busy');
                    if ($retryable) {
                        $order->update(['reference_id' => $externalOrderId]);
                    } else {
                        BundlePortalOrderFailureService::refund($order, $data['message'] ?? $data['error'] ?? 'Bundle Portal rejected the order.');
                    }
                    Log::error('BundlePortal: order failed', ['order_id' => $order->id, 'response' => $data]);
                }
            } catch (\Exception $e) {
                $order->update(['reference_id' => $externalOrderId]);
                Log::error('BundlePortal exception', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Returns the BundlePortal network value, or null if not handled (MTN is explicitly ignored).
     */
    private function getNetwork(string $name): ?string
    {
        if (stripos($name, 'mtn') !== false) {
            return null;
        }
        if (stripos($name, 'telecel') !== false) {
            return 'telecel';
        }
        if (stripos($name, 'at data') !== false || stripos($name, 'at big') !== false || stripos($name, 'airteltigo') !== false || stripos($name, 'ishare') !== false) {
            return 'airteltigo';
        }
        return null;
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) === 9 ? '0' . $phone : $phone;
    }
}
