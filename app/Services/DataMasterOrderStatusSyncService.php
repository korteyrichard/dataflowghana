<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataMasterOrderStatusSyncService
{
    private $baseUrl;
    private $secretKey;
    private $smsService;

    public function __construct(MoolreSmsService $smsService)
    {
        $this->baseUrl = config('services.datamaster.base_url');
        $this->secretKey = config('services.datamaster.secret_key');
        $this->smsService = $smsService;
    }

    public function syncOrderStatuses()
    {
        $processingOrders = Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->whereHas('products', function($query) {
                $query->where('name', 'like', '%mtn express%');
            })
            ->get();
        
        echo "Found {$processingOrders->count()} MTN Express orders to sync\n";
        
        foreach ($processingOrders as $order) {
            try {
                echo "Syncing order {$order->id} with reference {$order->reference_id}\n";
                $this->syncDataMasterOrderStatus($order);
            } catch (\Exception $e) {
                echo "Failed to sync order {$order->id}: {$e->getMessage()}\n";
            }
        }
    }

    private function syncDataMasterOrderStatus($order)
    {
        $orderNumber = $order->reference_id;
        
        if (!$orderNumber) {
            return;
        }

        try {
            $endpoint = $this->baseUrl . '/orders/status';
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json'
            ])->timeout(30)->get($endpoint, [
                'order_number' => $orderNumber
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success'] === true && isset($data['data'])) {
                    $orderData = $data['data'];
                    $deliveryStatus = $orderData['delivery_status'] ?? '';
                    $paymentStatus = $orderData['payment_status'] ?? '';
                    
                    $newStatus = $this->mapDataMasterStatus($deliveryStatus, $paymentStatus);
                    
                    if ($newStatus && $newStatus !== $order->status) {
                        $order->update(['status' => $newStatus]);
                        
                        if ($newStatus === 'completed' && $order->user && $order->user->phone) {
                            $message = "Your MTN Express order #{$order->id} for {$order->beneficiary_number} has been completed successfully. Thank you!";
                            $this->smsService->sendSms($order->user->phone, $message);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    private function mapDataMasterStatus($deliveryStatus, $paymentStatus)
    {
        // Both payment and delivery must be completed for order to be completed
        if ($deliveryStatus === 'completed' && $paymentStatus === 'completed') {
            return 'completed';
        }
        
        // If payment failed or was refunded, mark as cancelled
        if (in_array($paymentStatus, ['failed', 'refunded'])) {
            return 'cancelled';
        }
        
        // If payment is completed but delivery is pending, mark as processing
        if ($paymentStatus === 'completed' && $deliveryStatus === 'pending') {
            return 'processing';
        }
        
        // Default to processing for pending states
        if (in_array($paymentStatus, ['pending']) || in_array($deliveryStatus, ['pending'])) {
            return 'processing';
        }
        
        return null;
    }
}