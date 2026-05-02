<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MtnExpressOrderPusherService
{
    private $baseUrl;
    private $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('services.datamaster.base_url', 'https://user.datamastagh.shop/developer/api/v2');
        $this->secretKey = config('services.datamaster.secret_key');
    }

    public function pushOrderToApi(Order $order)
    {
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();

        foreach ($items as $item) {
            // Process all MTN products
            if (!$this->isMtnProduct($item->name)) {
                continue;
            }
            
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            
            if (empty($beneficiaryPhone) || !$variant) {
                continue;
            }

            $dataSize = $this->getDataSizeFromVariant($variant);
            
            if (!$dataSize) {
                continue;
            }

            $endpoint = $this->baseUrl . '/orders/place';
            $payload = [
                'beneficiary_number' => $this->formatPhone($beneficiaryPhone),
                'data_size' => $dataSize
            ];

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->secretKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($endpoint, $payload);

                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    if (isset($responseData['success']) && $responseData['success'] === true && isset($responseData['data']['order_number'])) {
                        $orderNumber = $responseData['data']['order_number'];
                        
                        $order->update([
                            'reference_id' => $orderNumber,
                            'api_status' => 'success'
                        ]);
                        
                        Log::info('Order pushed to DataMaster successfully', [
                            'order_id' => $order->id,
                            'order_number' => $orderNumber
                        ]);
                    } else {
                        $order->update(['api_status' => 'failed']);
                        Log::warning('DataMaster API response missing order_number', [
                            'order_id' => $order->id,
                            'response' => $responseData
                        ]);
                    }
                } else {
                    $order->update(['api_status' => 'failed']);
                    Log::error('DataMaster API call failed', [
                        'order_id' => $order->id,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }

            } catch (\Exception $e) {
                $order->update(['api_status' => 'failed']);
                Log::error('DataMaster API exception', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
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
        $productName = strtolower($productName);
        return stripos($productName, 'mtn') !== false;
    }
    
    private function getDataSizeFromVariant($variant)
    {
        if (isset($variant->variant_attributes['size'])) {
            $size = $variant->variant_attributes['size'];
            // Extract numeric value from size string (e.g., "1GB" -> 1, "1.5GB" -> 1.5)
            return (float)filter_var($size, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }
        
        return null;
    }
}