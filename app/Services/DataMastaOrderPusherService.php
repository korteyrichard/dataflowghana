<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataMastaOrderPusherService
{
    private string $baseUrl = 'https://www.datamastergh.shop';
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.datamasta.api_key', '');
    }

    public function pushOrderToApi(Order $order): void
    {
        if (empty($this->apiKey)) {
            Log::error('DataMasta API key not configured', ['order_id' => $order->id]);
            $order->update(['api_status' => 'failed']);
            return;
        }

        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();

        foreach ($items as $item) {
            if (!$this->isMtnProduct($item->name)) {
                continue;
            }

            $phone = $item->pivot->beneficiary_number;
            $variant = ProductVariant::find($item->pivot->product_variant_id);

            if (empty($phone) || !$variant) {
                Log::warning('DataMasta: missing phone or variant', ['order_id' => $order->id]);
                continue;
            }

            $capacity = $this->getCapacity($variant);

            if (!$capacity) {
                Log::warning('DataMasta: could not determine capacity', [
                    'order_id' => $order->id,
                    'attributes' => $variant->variant_attributes,
                ]);
                continue;
            }

            $payload = [
                'network'     => 'MTN',
                'phoneNumber' => $this->formatPhone($phone),
                'capacity'    => $capacity,
                'reference'   => 'DF-' . $order->id . '-' . time(),
            ];

            Log::info('DataMasta pushing order', ['order_id' => $order->id, 'payload' => $payload]);

            try {
                $response = Http::withHeaders([
                    'x-api-key'    => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])->timeout(30)->post($this->baseUrl . '/api/v1/purchase', $payload);

                $data = $response->json();

                Log::info('DataMasta API response', [
                    'order_id'    => $order->id,
                    'status_code' => $response->status(),
                    'response'    => $data,
                ]);

                if ($response->successful() && isset($data['reference'])) {
                    $order->update([
                        'reference_id' => $data['reference'],
                        'api_status'   => 'success',
                    ]);
                    Log::info('DataMasta order pushed successfully', [
                        'order_id'  => $order->id,
                        'reference' => $data['reference'],
                    ]);
                } else {
                    $order->update(['api_status' => 'failed']);
                    Log::error('DataMasta API error', [
                        'order_id' => $order->id,
                        'response' => $data,
                    ]);
                }
            } catch (\Exception $e) {
                $order->update(['api_status' => 'failed']);
                Log::error('DataMasta API exception', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    private function getCapacity(ProductVariant $variant): ?string
    {
        if (!isset($variant->variant_attributes['size'])) {
            return null;
        }

        $size = strtoupper(trim($variant->variant_attributes['size']));

        // Ensure format matches DataMasta exactly e.g. "1GB", "10GB"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(GB|MB)$/i', $size, $m)) {
            return $m[1] . strtoupper($m[2]);
        }

        return $size;
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 9) {
            return '0' . $phone;
        }
        return $phone;
    }

    private function isMtnProduct(string $productName): bool
    {
        return stripos($productName, 'mtn') !== false;
    }
}
