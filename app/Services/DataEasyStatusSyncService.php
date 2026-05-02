<?php

namespace App\Services;

use App\Models\Order;
use App\Services\MoolreSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataEasyStatusSyncService
{
    private $baseUrl;
    private $apiKey;
    private $smsService;

    public function __construct(MoolreSmsService $smsService)
    {
        $this->baseUrl = config('services.dataeasy.base_url', 'https://dataeasy.onrender.com/api/v1');
        $this->apiKey = config('services.dataeasy.api_key');
        $this->smsService = $smsService;
    }

    public function syncOrderStatuses()
    {
        // Get MTN orders that were pushed to DataEasy (have a UUID reference_id)
        // UUIDs from DataEasy typically have dashes. DataMaster ids are usually numeric strings.
        // We'll filter for MTN orders with reference_id that are not fully numeric or are longer.
        $processingOrders = Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->where('network', 'like', '%mtn%')
            ->get();
        
        Log::info('Found ' . $processingOrders->count() . ' MTN orders to check for DataEasy status sync');
        
        foreach ($processingOrders as $order) {
            // Check if this referenceId looks like a DataEasy UUID (e.g. 8-4-4-4-12 chars)
            if (strlen($order->reference_id) < 20 && is_numeric($order->reference_id)) {
                // Skiping DataMaster or other numeric IDs
                continue;
            }

            try {
                $this->syncOrderStatus($order);
            } catch (\Exception $e) {
                Log::error("Failed to sync status for order {$order->id}: {$e->getMessage()}");
            }
        }
    }

    private function syncOrderStatus(Order $order)
    {
        $referenceId = $order->reference_id;
        
        try {
            $endpoint = $this->baseUrl . '/orders/' . $referenceId;
            
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json'
            ])->timeout(30)->get($endpoint);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success'] === true && isset($data['order'])) {
                    $orderData = $data['order'];
                    $deliveryStatus = $orderData['deliveryStatus'] ?? '';
                    
                    $newStatus = $this->mapDataEasyStatus($deliveryStatus);
                    
                    if ($newStatus && $newStatus !== $order->status) {
                        $order->update(['status' => $newStatus]);
                        
                        Log::info('DataEasy order status updated', [
                            'order_id' => $order->id,
                            'old_status' => $order->getOriginal('status'),
                            'new_status' => $newStatus,
                            'external_status' => $deliveryStatus
                        ]);

                        if ($newStatus === 'completed' && $order->user && $order->user->phone) {
                            $message = "Your MTN order #{$order->id} to {$order->beneficiary_number} (size: {$order->total}) has been completed successfully. Thank you for using DF-Ghana!";
                            $this->smsService->sendSms($order->user->phone, $message);
                        }
                    }
                }
            } else if ($response->status() === 404) {
                 // Might not be a DataEasy order, skipping
            }
        } catch (\Exception $e) {
            Log::error('DataEasy Status check error for order ' . $order->id . ': ' . $e->getMessage());
        }
    }

    private function mapDataEasyStatus($deliveryStatus)
    {
        $statusMap = [
            'Pending' => 'processing',
            'Processing' => 'processing',
            'Delivered' => 'completed',
            'Failed' => 'cancelled',
        ];

        return $statusMap[$deliveryStatus] ?? null;
    }
}
