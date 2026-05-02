<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\MoolreSmsService;

class CodeCraftOrderPusherService
{
    private $apiKey = '';
    private $baseUrl = 'https://api.codecraftnetwork.com/api';

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for CodeCraft API push', ['order_id' => $order->id]);
        
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        Log::info('Order has items', ['count' => $items->count()]);
        
        $processedItems = 0;

        foreach ($items as $item) {
            Log::info('Processing item', ['name' => $item->name]);
            
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            $gig = $variant && isset($variant->variant_attributes['size']) ? (int)filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $network = $this->getNetworkFromProduct($item->name);
            
            if (empty($beneficiaryPhone) || !$network || !$gig) {
                Log::warning('Missing required order data', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'beneficiary' => $beneficiaryPhone,
                    'network' => $network,
                    'gig' => $gig
                ]);
                continue;
            }
            
            $processedItems++;

            $endpoint = $this->getEndpoint($network);
            
            $payload = [
                'recipient_number' => $this->formatPhone($beneficiaryPhone),
                'gig' => (string)$gig,
                'network' => str_replace('_BIGTIME', '', $network)
            ];
            
            Log::info('Sending to CodeCraft API', ['endpoint' => $endpoint, 'payload' => $payload]);

            try {
                $response = Http::timeout(30)
                    ->withHeaders(['x-api-key' => $this->apiKey])
                    ->post($endpoint, $payload);
                
                $statusCode = $response->status();
                $responseData = $response->json();
                
                Log::info('CodeCraft API Response', [
                    'status_code' => $statusCode,
                    'response' => $responseData
                ]);

                if ($statusCode == 200 && isset($responseData['reference_id'])) {
                    $updateData = [
                        'reference_id' => $responseData['reference_id'],
                        'order_pusher_status' => 'success'
                    ];
                    
                    if ($network === 'AT') {
                        $updateData['status'] = 'completed';
                    }
                    
                    $order->update($updateData);
                    
                    // Send SMS if Ishare order completed and user has phone
                    try {
                        if ($network === 'AT' && $order->user && $order->user->phone) {
                            $smsService = new MoolreSmsService();
                            $productName = $item->name ?? 'Ishare Data';
                            $message = "Your order #{$order->id} for {$productName} to {$item->pivot->beneficiary_number} has been completed. Total: GHS " . number_format($order->total, 2);
                            $smsService->sendSms($order->user->phone, $message);
                        }
                    } catch (\Exception $smsException) {
                        Log::warning('SMS sending failed but order was successful', [
                            'order_id' => $order->id,
                            'error' => $smsException->getMessage()
                        ]);
                    }
                    
                    Log::info('Order sent successfully to CodeCraft', ['reference_id' => $responseData['reference_id']]);
                } else {
                    $order->update(['order_pusher_status' => 'failed']);
                    $message = $responseData['message'] ?? 'Unknown error';
                    Log::error('CodeCraft API Error', [
                        'status_code' => $statusCode,
                        'message' => $message
                    ]);
                }

            } catch (\Exception $e) {
                $order->update(['order_pusher_status' => 'failed']);
                Log::error('CodeCraft API Exception', [
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        // If no items were processed, keep the status as disabled
        if ($processedItems === 0) {
            Log::info('No items were processed for order, keeping status as disabled', ['order_id' => $order->id]);
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
    
    private function getNetworkFromProduct($productName)
    {
        $productName = strtolower($productName);
        
        if (stripos($productName, 'telecel') !== false) {
            return 'TELECEL';
        } elseif (stripos($productName, 'ishare') !== false) {
            return 'AT';
        } elseif (stripos($productName, 'bigtime') !== false) {
            return 'AT_BIGTIME';
        }
        
        // MTN orders should not be processed by CodeCraft
        if (stripos($productName, 'mtn') !== false) {
            Log::info('MTN order detected, skipping CodeCraft processing', ['product_name' => $productName]);
            return null;
        }
        
        return null;
    }
    
    private function getEndpoint($network)
    {
        if (in_array($network, ['AT_BIGTIME'])) {
            return $this->baseUrl . '/special.php';
        }
        
        return $this->baseUrl . '/initiate.php';
    }
    
    public function checkOrderStatus($referenceId, $isBigTime = false)
    {
        $endpoint = $isBigTime 
            ? $this->baseUrl . '/response_big_time.php'
            : $this->baseUrl . '/response_regular.php';
            
        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->get($endpoint, ['reference_id' => $referenceId]);
                
            $responseData = $response->json();
            
            Log::info('Order status check response', [
                'reference_id' => $referenceId,
                'response' => $responseData
            ]);
            
            return $responseData;
            
        } catch (\Exception $e) {
            Log::error('Order status check failed', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}