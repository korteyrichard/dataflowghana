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
    private $clientEmail = '';

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for CodeCraft API push', ['order_id' => $order->id]);
        
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        Log::info('Order has items', ['count' => $items->count()]);

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

            $referenceId = $this->generateReferenceId();
            $endpoint = $this->getEndpoint($network);
            
            $payload = [
                'agent_api' => $this->apiKey,
                'recipient_number' => $this->formatPhone($beneficiaryPhone),
                'gig' => (string)$gig,
                'reference_id' => $referenceId,
                'client_email' => $this->clientEmail
            ];
            
            if (in_array($network, ['MTN_BIGTIME', 'AT_BIGTIME'])) {
                $payload['network'] = str_replace('_BIGTIME', '', $network);
            } else {
                $payload['network'] = $network;
                $payload['customer_name'] = $order->user->name ?? 'Customer';
                $payload['customer_tel'] = $order->user->phone ?? $beneficiaryPhone;
            }
            
            Log::info('Sending to CodeCraft API', ['endpoint' => $endpoint, 'payload' => $payload]);

            try {
                $response = Http::timeout(30)->post($endpoint, $payload);
                
                $statusCode = $response->status();
                $responseData = $response->json();
                
                Log::info('CodeCraft API Response', [
                    'status_code' => $statusCode,
                    'response' => $responseData,
                    'reference_id' => $referenceId
                ]);

                if ($statusCode == 200) {
                    $updateData = [
                        'reference_id' => $referenceId,
                        'api_status' => 'success'
                    ];
                    
                    if ($network === 'AT') {
                        $updateData['status'] = 'completed';
                    }
                    
                    $order->update($updateData);
                    
                    // Send SMS if Ishare order completed and user has phone
                    if ($network === 'AT' && $order->user && $order->user->phone) {
                        $smsService = new MoolreSmsService();
                        $message = "Your order #{$order->id} for {$order->products->first()->name} to {$item->pivot->beneficiary_number} has been completed. Total: GHS " . number_format($order->total, 2);
                        $smsService->sendSms($order->user->phone, $message);
                    }
                    
                    Log::info('Order sent successfully to CodeCraft', ['reference_id' => $referenceId]);
                } else {
                    // API call failed
                    $order->update(['api_status' => 'failed']);
                    
                    $message = $responseData['message'] ?? 'Unknown error';
                    Log::error('CodeCraft API Error', [
                        'status_code' => $statusCode,
                        'message' => $message,
                        'reference_id' => $referenceId
                    ]);
                }

            } catch (\Exception $e) {
                // API call exception
                $order->update(['api_status' => 'failed']);
                
                Log::error('CodeCraft API Exception', [
                    'message' => $e->getMessage(),
                    'reference_id' => $referenceId
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
    
    private function getNetworkFromProduct($productName)
    {
        $productName = strtolower($productName);
        
        if (stripos($productName, 'mtn') !== false) {
            return stripos($productName, 'big') !== false ? 'MTN_BIGTIME' : 'MTN';
        } elseif (stripos($productName, 'telecel') !== false) {
            return 'TELECEL';
        } elseif (stripos($productName, 'ishare') !== false) {
            return 'AT';
        } elseif (stripos($productName, 'bigtime') !== false) {
            return 'AT_BIGTIME';
        }
        
        return null;
    }
    
    private function getEndpoint($network)
    {
        if (in_array($network, ['MTN_BIGTIME', 'AT_BIGTIME'])) {
            return 'https://api.codecraftnetwork.com/api/special.php';
        }
        
        return 'https://api.codecraftnetwork.com/api/initiate.php';
    }
    
    private function generateReferenceId()
    {
        return strtoupper(Str::random(5) . '-' . Str::random(5) . '-' . Str::random(6) . '-' . Str::random(5) . '-' . rand(10000, 99999));
    }
}