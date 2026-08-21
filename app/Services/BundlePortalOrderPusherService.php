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
        $this->baseUrl = config('services.bundleportal.base_url', 'https://api.bundleportal.com/v1');
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

            $externalOrderId = 'SD-' . $order->id . '-' . time();
            $payload = [
                'action'       => 'place_order',
                'network'      => $network,
                'recipient'    => $this->formatPhone($phone),
                'package_size' => $gb,
                'order_id'     => $externalOrderId,
            ];

            try {
                $response = Http::timeout(30)
                    ->withHeaders(['x-api-key' => $this->apiKey, 'Content-Type' => 'application/json'])
                    ->post($this->baseUrl, $payload);

                $data = $response->json();

                Log::info('BundlePortal response', ['order_id' => $order->id, 'status' => $response->status(), 'body' => $data]);

                if ($response->status() === 409 && ($data['error'] ?? '') === 'pending_order') {
                    $order->update(['api_status' => 'failed']);
                    Log::warning('BundlePortal: pending_order conflict', ['order_id' => $order->id]);
                    return;
                }

                if (($data['success'] ?? false) && isset($data['data']['reference'])) {
                    $order->update([
                        'reference_id' => $data['data']['reference'],
                        'api_status'   => 'success',
                    ]);
                    Log::info('BundlePortal order placed', ['reference' => $data['data']['reference']]);
                } else {
                    $order->update(['api_status' => 'failed']);
                    Log::error('BundlePortal: order failed', ['order_id' => $order->id, 'response' => $data]);
                }
            } catch (\Exception $e) {
                $order->update(['api_status' => 'failed']);
                Log::error('BundlePortal exception', ['order_id' => $order->id, 'error' => $e->getMessage()]);
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
            Log::error('BundlePortal status check failed', ['reference' => $reference, 'error' => $e->getMessage()]);
            return null;
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
