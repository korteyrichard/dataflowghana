<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataSourceOrderPusherService
{
    private $baseUrl;
    private $apiKey;
    private $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('services.datasource.base_url');
        $this->apiKey = config('services.datasource.api_key');
        $this->secretKey = config('services.datasource.secret_key');
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
        $endpoint = '/api/v1/order/single/create';
        $dataSize = $sharedBundle / 1000;
        $timestamp = time();
        $method = 'POST';

        $bodyArray = [
            'beneficiary_number' => $this->formatPhone($beneficiaryPhone),
            'data_size' => $dataSize
        ];
        $body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);

        $signatureString = $timestamp . $method . $endpoint . $body;
        $signature = hash_hmac('sha256', $signatureString, $this->secretKey);

        Log::info('DataSource Order Pusher - Pushing order', [
            'order_id' => $order->id,
            'base_url' => $this->baseUrl,
            'endpoint' => $endpoint,
            'beneficiary' => $beneficiaryPhone,
            'data_size' => $dataSize,
            'signature_string' => $signatureString,
            'signature' => $signature
        ]);

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(60)->connectTimeout(10)->post($this->baseUrl . $endpoint, $bodyArray);

            if ($response->successful()) {
                $responseData = $response->json();

                Log::info('DataSource Order Pusher API response', [
                    'order_id' => $order->id,
                    'response' => $responseData
                ]);

                if (isset($responseData['success']) && $responseData['success'] === true && isset($responseData['batchreference'])) {
                    $batchReference = $responseData['batchreference'];

                    $order->update([
                        'reference_id' => $batchReference,
                        'api_status' => 'success'
                    ]);

                    Log::info('Order pushed to DataSource Order Pusher successfully', [
                        'order_id' => $order->id,
                        'batch_reference' => $batchReference
                    ]);
                } else {
                    $order->update(['api_status' => 'failed']);
                    Log::warning('DataSource Order Pusher API response missing batchreference', [
                        'order_id' => $order->id,
                        'response' => $responseData
                    ]);
                }
            } else {
                $order->update(['api_status' => 'failed']);
                Log::error('DataSource Order Pusher API call failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            $order->update(['api_status' => 'failed']);
            Log::error('DataSource Order Pusher API exception', [
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

    public function pushBulkOrderToApi(array $orders, array $orderData)
    {
        $endpoint = '/api/v1/order/bulk/create';
        $timestamp = time();
        $method = 'POST';

        $bulkData = [];
        foreach ($orderData as $data) {
            $bulkData[] = [
                'beneficiary_number' => $this->formatPhone($data['phone']),
                'data_size' => (float)filter_var($data['bundle_size'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
            ];
        }

        $bodyArray = ['orders' => $bulkData];
        $body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);

        $signatureString = $timestamp . $method . $endpoint . $body;
        $signature = hash_hmac('sha256', $signatureString, $this->secretKey);

        Log::info('DataSource Bulk Order Pusher - Pushing orders', [
            'order_count' => count($orders),
            'base_url' => $this->baseUrl,
            'endpoint' => $endpoint,
            'bulk_data' => $bulkData,
            'signature' => $signature
        ]);

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->timeout(60)->connectTimeout(10)->post($this->baseUrl . $endpoint, $bodyArray);

            if ($response->successful()) {
                $responseData = $response->json();

                Log::info('DataSource Bulk Order Pusher API response', [
                    'order_count' => count($orders),
                    'response' => $responseData
                ]);

                if (isset($responseData['success']) && $responseData['success'] === true) {
                    // Store bulk reference ID if provided - ALL orders get the same reference
                    $bulkReference = $responseData['bulk_reference'] ?? $responseData['batchreference'] ?? null;
                    
                    if ($bulkReference) {
                        // Update all orders with success status and the SAME bulk reference
                        foreach ($orders as $order) {
                            // Make sure we have an Order model, not an array
                            if (is_array($order)) {
                                $orderModel = Order::find($order['id']);
                            } else {
                                $orderModel = $order;
                            }
                            
                            if ($orderModel) {
                                $orderModel->update([
                                    'api_status' => 'success',
                                    'reference_id' => $bulkReference  // Same reference for all orders
                                ]);
                            }
                        }
                        
                        Log::info('Bulk orders pushed to DataSource successfully - all orders share same reference', [
                            'order_count' => count($orders),
                            'bulk_reference' => $bulkReference,
                            'order_ids' => collect($orders)->map(function($order) {
                                return is_array($order) ? $order['id'] : $order->id;
                            })->toArray()
                        ]);
                    } else {
                        // No reference provided, just update status
                        foreach ($orders as $order) {
                            // Make sure we have an Order model, not an array
                            if (is_array($order)) {
                                $orderModel = Order::find($order['id']);
                            } else {
                                $orderModel = $order;
                            }
                            
                            if ($orderModel) {
                                $orderModel->update(['api_status' => 'success']);
                            }
                        }
                        
                        Log::warning('Bulk orders pushed successfully but no reference ID provided', [
                            'order_count' => count($orders)
                        ]);
                    }
                } else {
                    // Update all orders with failed status
                    foreach ($orders as $order) {
                        // Make sure we have an Order model, not an array
                        if (is_array($order)) {
                            $orderModel = Order::find($order['id']);
                        } else {
                            $orderModel = $order;
                        }
                        
                        if ($orderModel) {
                            $orderModel->update(['api_status' => 'failed']);
                        }
                    }
                    Log::warning('DataSource Bulk Order Pusher API response indicates failure', [
                        'order_count' => count($orders),
                        'response' => $responseData
                    ]);
                }
            } else {
                foreach ($orders as $order) {
                    // Make sure we have an Order model, not an array
                    if (is_array($order)) {
                        $orderModel = Order::find($order['id']);
                    } else {
                        $orderModel = $order;
                    }
                    
                    if ($orderModel) {
                        $orderModel->update(['api_status' => 'failed']);
                    }
                }
                Log::error('DataSource Bulk Order Pusher API call failed', [
                    'order_count' => count($orders),
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            foreach ($orders as $order) {
                // Make sure we have an Order model, not an array
                if (is_array($order)) {
                    $orderModel = Order::find($order['id']);
                } else {
                    $orderModel = $order;
                }
                
                if ($orderModel) {
                    $orderModel->update(['api_status' => 'failed']);
                }
            }
            Log::error('DataSource Bulk Order Pusher API exception', [
                'order_count' => count($orders),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function isMtnProduct($productName)
    {
        return stripos($productName, 'mtn') !== false;
    }
}
