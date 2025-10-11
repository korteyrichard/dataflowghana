<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderPusherService
{
    private $baseUrl = '';
    private $apiKey = '';

    public function pushOrderToApi(Order $order)
    {
        Log::info('Processing order for API push', ['order_id' => $order->id]);
        
        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();
        Log::info('Order has items', ['count' => $items->count()]);

        foreach ($items as $item) {
            Log::info('Processing item', ['name' => $item->name]);
            
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            Log::info('bundle size', ['variant' => $variant]);

            $sizeInGB = $variant && isset($variant->variant_attributes['size']) ? (int)filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $sharedBundle = $sizeInGB * 1000 * $item->pivot->quantity;
            $networkId = $this->getNetworkIdFromProduct($item->name);
            
            Log::info('Item details', [
                'product' => $item->name,
                'beneficiary' => $beneficiaryPhone,
                'bundle' => $sharedBundle,
                'network_id' => $networkId
            ]);

            if (empty($beneficiaryPhone)) {
                Log::warning('No beneficiary phone found for item, skipping');
                continue;
            }

            if (!$networkId || !$sharedBundle) {
                Log::warning('Skipping non-MTN product or missing data', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'product' => $item->name
                ]);
                continue;
            }

            $endpoint = $this->baseUrl . '/buy-other-package';
            $payload = [
                'recipient_msisdn' => $this->formatPhone($beneficiaryPhone),
                'network_id' => $networkId,
                'shared_bundle' => $sharedBundle
            ];
            
            Log::info('Sending to API', ['endpoint' => $endpoint, 'payload' => $payload]);

            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($endpoint, $payload);

                Log::info('API Response', [
                    'status_code' => $response->status(),
                    'body' => $response->body()
                ]);

                // Check if the API call was successful and save the transaction code
                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    // Add detailed logging to debug the response structure
                    Log::info('Detailed API response data', [
                        'order_id' => $order->id,
                        'raw_response' => $response->body(),
                        'parsed_response' => $responseData,
                        'has_success_key' => isset($responseData['success']),
                        'success_value' => $responseData['success'] ?? 'not_set',
                        'has_transaction_code' => isset($responseData['transaction_code']),
                        'transaction_code_value' => $responseData['transaction_code'] ?? 'not_set'
                    ]);
                    
                    if (isset($responseData['success']) && $responseData['success'] === true && isset($responseData['transaction_code'])) {
                        $transactionCode = $responseData['transaction_code'];
                        
                        Log::info('About to update order with reference_id', [
                            'order_id' => $order->id,
                            'current_reference_id' => $order->reference_id,
                            'new_transaction_code' => $transactionCode
                        ]);
                        
                        // Try to update the order and check if it was successful
                        $updateResult = $order->update([
                            'reference_id' => $transactionCode,
                            'api_status' => 'success'
                        ]);
                        
                        // Refresh the order from database to verify the update
                        $order->refresh();
                        
                        Log::info('Order update result', [
                            'order_id' => $order->id,
                            'update_successful' => $updateResult,
                            'reference_id_after_update' => $order->reference_id,
                            'transaction_code_matches' => $order->reference_id === $transactionCode,
                            'api_status' => $order->api_status
                        ]);
                        
                        // Update order status to processing since we have a transaction
                        // if ($order->status === 'pending') {
                        //     $statusUpdateResult = $order->update(['status' => 'processing']);
                        //     $order->refresh();
                            
                        //     Log::info('Order status update result', [
                        //         'order_id' => $order->id,
                        //         'status_update_successful' => $statusUpdateResult,
                        //         'current_status' => $order->status
                        //     ]);
                        // }
                    } else {
                        // API response indicates failure
                        $order->update(['api_status' => 'failed']);
                        
                        Log::warning('API response indicates failure or missing transaction code', [
                            'order_id' => $order->id,
                            'response' => $responseData,
                            'success_check' => isset($responseData['success']) ? ($responseData['success'] === true ? 'true' : 'false_or_not_boolean') : 'key_missing',
                            'transaction_code_check' => isset($responseData['transaction_code']) ? 'present' : 'missing'
                        ]);
                    }
                } else {
                    // API call failed
                    $order->update(['api_status' => 'failed']);
                    
                    Log::error('API call failed', [
                        'order_id' => $order->id,
                        'status_code' => $response->status(),
                        'response' => $response->body()
                    ]);
                }

            } catch (\Exception $e) {
                // API call exception
                $order->update(['api_status' => 'failed']);
                
                Log::error('API Error', [
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
        
        return $phone;
    }
    
    private function getNetworkIdFromProduct($productName)
    {
        $productName = strtolower($productName);
        
        if (stripos($productName, 'mtn') !== false) {
            return 3;
        }
        
        return null;
    }
}