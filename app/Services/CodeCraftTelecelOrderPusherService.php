<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CodeCraftTelecelOrderPusherService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.codecraft.base_url');
        $this->apiKey = config('services.codecraft.api_key', '');

        if (empty($this->apiKey)) {
            Log::error('CodeCraft Telecel API key is not configured');
        }
    }

    public function pushOrderToApi(Order $order)
    {
        if (empty($this->apiKey)) {
            Log::error('CodeCraft Telecel API key is not configured, skipping order push', ['order_id' => $order->id]);
            $order->update(['api_status' => 'failed']);
            return;
        }

        $apiEnabled = (bool) Setting::get('codecraft_telecel_order_pusher_enabled', 0);

        if (!$apiEnabled) {
            Log::info('CodeCraft Telecel API is disabled, skipping order push', ['order_id' => $order->id]);
            $order->update(['api_status' => 'disabled']);
            return;
        }

        if (stripos($order->network, 'telecel') === false) {
            Log::warning('CodeCraft Telecel: Order is not a Telecel order, skipping', [
                'order_id' => $order->id,
                'network' => $order->network
            ]);
            return;
        }

        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();

        foreach ($items as $item) {
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            $gig = $variant && isset($variant->variant_attributes['size'])
                ? (int) filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT)
                : 0;

            if (empty($beneficiaryPhone) || !$gig) {
                Log::warning('CodeCraft Telecel: Missing required order data', [
                    'order_id' => $order->id,
                    'beneficiary' => $beneficiaryPhone,
                    'gig' => $gig
                ]);
                continue;
            }

            $payload = [
                'recipient_number' => $this->formatPhone($beneficiaryPhone),
                'gig' => (string) $gig,
                'network' => 'TELECEL'
            ];

            Log::info('Sending Telecel order to CodeCraft API', [
                'endpoint' => $this->baseUrl . '/initiate.php',
                'payload' => $payload
            ]);

            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'x-api-key' => $this->apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->post($this->baseUrl . '/initiate.php', $payload);

                $responseData = $response->json();

                Log::info('CodeCraft Telecel API Response', [
                    'status_code' => $response->status(),
                    'response' => $responseData
                ]);

                if ($response->status() == 200 && (isset($responseData['reference_id']) || isset($responseData['data']['reference_id']))) {
                    $refId = $responseData['reference_id'] ?? $responseData['data']['reference_id'];
                    $order->update([
                        'reference_id' => $refId,
                        'api_status' => 'success'
                    ]);
                    Log::info('Telecel order sent to CodeCraft successfully', ['reference_id' => $refId]);
                } else {
                    $order->update(['api_status' => 'failed']);
                    Log::error('CodeCraft Telecel API Error', [
                        'status_code' => $response->status(),
                        'message' => $responseData['message'] ?? 'Unknown error',
                        'order_id' => $order->id
                    ]);
                }
            } catch (\Exception $e) {
                $order->update(['api_status' => 'failed']);
                Log::error('CodeCraft Telecel API Exception', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage()
                ]);
            }
        }
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 9) {
            return '0' . $phone;
        }
        return $phone;
    }
}
