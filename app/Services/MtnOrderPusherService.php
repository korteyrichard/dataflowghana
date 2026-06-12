<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataSourceOrderPusherService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.order_pusher.base_url', 'https://agent.jaybartservices.com/api/v1');
        $this->apiKey = config('services.order_pusher.api_key');
    }

    public function pushOrderToApi(Order $order)
    {
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();

        foreach ($items as $item) {
            if (!$this->isMtnProduct($item->name)) {
                continue;
            }

            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);

            if (empty($beneficiaryPhone) || !$variant) {
                continue;
            }

            $sizeInGB = isset($variant->variant_attributes['size']) 
                ? (int)filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT) 
                : 0;
            $sharedBundle = $sizeInGB * 1000 * $item->pivot->quantity;

            if (!$sharedBundle) {
                continue;
            }

            $this->pushSingleOrder($order, $beneficiaryPhone, $sharedBundle);
        }
    }

    private function pushSingleOrder(Order $order, $beneficiaryPhone, $sharedBundle)
    {
        $endpoint = $this->baseUrl . '/buy-other-package';
        $payload = [
            'recipient_msisdn' => $this->formatPhone($beneficiaryPhone),
            'network_id' => 3,
            'shared_bundle' => $sharedBundle
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(30)->post($endpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['success']) && $responseData['success'] === true && isset($responseData['transaction_code'])) {
                    $transactionCode = $responseData['transaction_code'];

                    $order->update([
                        'reference_id' => $transactionCode,
                        'api_status' => 'success'
                    ]);

                    Log::info('Order pushed to MTN Order Pusher successfully', [
                        'order_id' => $order->id,
                        'transaction_code' => $transactionCode
                    ]);
                } else {
                    $order->update(['api_status' => 'failed']);
                    Log::warning('MTN Order Pusher API response missing transaction_code', [
                        'order_id' => $order->id,
                        'response' => $responseData
                    ]);
                }
            } else {
                $order->update(['api_status' => 'failed']);
                Log::error('MTN Order Pusher API call failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $order->update(['api_status' => 'failed']);
            Log::error('MTN Order Pusher API exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
            return $phone;
        }

        if (strlen($phone) == 9) {
            return '0' . $phone;
        }

        return $phone;
    }

    private function isMtnProduct($productName)
    {
        return stripos($productName, 'mtn') !== false;
    }
}
