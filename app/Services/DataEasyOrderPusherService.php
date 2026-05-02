<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataEasyOrderPusherService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.dataeasy.base_url', 'https://dataeasy.onrender.com/api/v1');
        $this->apiKey = config('services.dataeasy.api_key');
    }

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for DataEasy API push', ['order_id' => $order->id]);
        
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        
        foreach ($items as $item) {
            // Only handle MTN orders
            if (!$this->isMtnProduct($item->name)) {
                Log::info('Skipping non-MTN product for DataEasy', ['product' => $item->name]);
                continue;
            }
            
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = ProductVariant::find($item->pivot->product_variant_id);
            
            if (empty($beneficiaryPhone) || !$variant) {
                Log::warning('Missing beneficiary or variant for DataEasy push', ['order_id' => $order->id]);
                continue;
            }

            $packageId = $this->getPackageIdFromVariant($variant);
            
            if (!$packageId) {
                Log::warning('Could not determine packageId for DataEasy', [
                    'order_id' => $order->id,
                    'variant_id' => $variant->id,
                    'attributes' => $variant->variant_attributes
                ]);
                continue;
            }

            $endpoint = $this->baseUrl . '/orders';
            $payload = [
                'network' => 'MTN',
                'items' => [
                    [
                        'packageId' => $packageId,
                        'phoneNumber' => $this->formatPhone($beneficiaryPhone)
                    ]
                ]
            ];

            try {
                $response = Http::withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($endpoint, $payload);

                Log::info('DataEasy API Response', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    if (isset($responseData['success']) && $responseData['success'] === true && isset($responseData['order']['id'])) {
                        $externalId = $responseData['order']['id'];
                        
                        $order->update([
                            'reference_id' => $externalId,
                            'api_status' => 'success'
                        ]);
                        
                        Log::info('Order pushed to DataEasy successfully', [
                            'order_id' => $order->id,
                            'external_id' => $externalId
                        ]);
                    } else {
                        $order->update(['api_status' => 'failed']);
                        Log::warning('DataEasy API response failed', [
                            'order_id' => $order->id,
                            'response' => $responseData
                        ]);
                    }
                } else {
                    $order->update(['api_status' => 'failed']);
                    Log::error('DataEasy API call failed', [
                        'order_id' => $order->id,
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }

            } catch (\Exception $e) {
                $order->update(['api_status' => 'failed']);
                Log::error('DataEasy API Exception', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage()
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
    
    private function getPackageIdFromVariant(ProductVariant $variant)
    {
        // Check if dataeasy_package_id is directly available in attributes
        if (isset($variant->variant_attributes['dataeasy_package_id'])) {
            return $variant->variant_attributes['dataeasy_package_id'];
        }
        
        // Check if package_id is generic
        if (isset($variant->variant_attributes['package_id'])) {
             // If it's something like 'mtn-5gb' already, use it. But in previous providers it was often an integer.
             $val = $variant->variant_attributes['package_id'];
             if (is_string($val) && strpos($val, 'mtn-') === 0) {
                 return $val;
             }
        }
        
        // Otherwise, try to build it from size
        if (isset($variant->variant_attributes['size'])) {
            $size = strtolower($variant->variant_attributes['size']);
            // Standard format is mtn-1gb, mtn-500mb etc.
            // If size is "1GB", it becomes "mtn-1gb"
            return "mtn-" . $size;
        }
        
        return null;
    }
}
