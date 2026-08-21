<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RexamartOrderPusherService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.rexamart.com';

    public function __construct()
    {
        $this->apiKey = config('services.rexamart.api_key', env('REXAMART_API_KEY', ''));
    }

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for Rexamart API push', ['order_id' => $order->id]);

        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();

        foreach ($items as $item) {
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            $plan = $variant && isset($variant->variant_attributes['size']) 
                ? (string)(int)filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT) 
                : null;

            if (empty($beneficiaryPhone) || !$plan) {
                Log::warning('Missing required order data for Rexamart', [
                    'order_id' => $order->id,
                    'beneficiary' => $beneficiaryPhone,
                    'plan' => $plan,
                ]);
                continue;
            }

            $payload = [
                'phone_number' => $this->formatPhone($beneficiaryPhone),
                'network'      => 'Telecel',
                'plan'         => $plan,
                'reference'    => 'DF-' . $order->id,
            ];

            Log::info('Sending to Rexamart API', ['payload' => $payload]);

            try {
                $response = Http::timeout(30)
                    ->acceptJson()
                    ->withHeaders([
                        'X-API-Key'  => $this->apiKey,
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
                        'Accept'     => 'application/json',
                        'Origin'     => 'https://api.rexamart.com',
                        'Referer'    => 'https://api.rexamart.com/',
                    ])
                    ->post($this->baseUrl . '/orders', $payload);

                $statusCode = $response->status();
                $responseData = $response->json();

                Log::info('Rexamart API Response', [
                    'status_code' => $statusCode,
                    'response'    => $responseData,
                ]);

                if ($statusCode === 200 && isset($responseData['success']) && $responseData['success']) {
                    $order->update([
                        'reference_id' => $responseData['data']['order_id'] ?? null,
                        'api_status'   => 'success',
                    ]);
                    Log::info('Order sent successfully to Rexamart', ['order_id' => $responseData['data']['order_id'] ?? null]);
                } else {
                    $order->update(['api_status' => 'failed']);
                    Log::error('Rexamart API Error', [
                        'status_code' => $statusCode,
                        'error'       => $responseData['error'] ?? 'Unknown error',
                        'error_code'  => $responseData['error_code'] ?? null,
                    ]);
                }
            } catch (\Exception $e) {
                $order->update(['api_status' => 'failed']);
                Log::error('Rexamart API Exception', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            }
        }
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 9) {
            return '0' . $phone;
        }

        return $phone;
    }
}
