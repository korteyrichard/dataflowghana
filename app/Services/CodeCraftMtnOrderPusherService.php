<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CodeCraftMtnOrderPusherService
{
    private $apiKey;
    private $baseUrl = 'https://api.codecraftnetwork.com/api';

    public function __construct()
    {
        $this->apiKey = config('services.codecraft_mtn.api_key', '');

        if (empty($this->apiKey)) {
            Log::error('CodeCraft MTN API key is not configured');
        }
    }

    public function pushOrderToApi(Order $order)
    {
        if (empty($this->apiKey)) {
            Log::error('CodeCraft MTN API key is not configured, skipping order push', ['order_id' => $order->id]);
            $order->update(['api_status' => 'failed']);
            return;
        }

        $items = $order->products()->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')->get();

        foreach ($items as $item) {
            $beneficiaryPhone = $item->pivot->beneficiary_number;
            $variant = \App\Models\ProductVariant::find($item->pivot->product_variant_id);
            $gig = $variant && isset($variant->variant_attributes['size'])
                ? (int) filter_var($variant->variant_attributes['size'], FILTER_SANITIZE_NUMBER_INT)
                : 0;
            $network = $this->getNetworkFromProduct($item->name);

            if (empty($beneficiaryPhone) || !$network || !$gig) {
                Log::warning('CodeCraft MTN: Missing required order data', [
                    'order_id' => $order->id,
                    'beneficiary' => $beneficiaryPhone,
                    'network' => $network,
                    'gig' => $gig
                ]);
                continue;
            }

            $isBigTime = stripos($item->name, 'big') !== false;
            $endpoint = $isBigTime
                ? $this->baseUrl . '/special.php'
                : $this->baseUrl . '/initiate.php';

            $payload = [
                'recipient_number' => $this->formatPhone($beneficiaryPhone),
                'gig' => (string) $gig,
                'network' => 'MTN'
            ];

            Log::info('Sending MTN order to CodeCraft API', [
                'endpoint' => $endpoint,
                'payload' => $payload,
                'is_big_time' => $isBigTime
            ]);

            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'x-api-key' => $this->apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->post($endpoint, $payload);

                $responseData = $response->json();

                Log::info('CodeCraft MTN API Response', [
                    'status_code' => $response->status(),
                    'response' => $responseData
                ]);

                if ($response->status() == 200 && (isset($responseData['reference_id']) || isset($responseData['data']['reference_id']))) {
                    $refId = $responseData['reference_id'] ?? $responseData['data']['reference_id'];
                    $order->update([
                        'reference_id' => $refId,
                        'api_status' => 'success'
                    ]);
                    Log::info('MTN order sent to CodeCraft successfully', ['reference_id' => $refId]);
                } elseif ($isBigTime && $response->status() == 404) {
                    // Fallback to regular endpoint
                    $fallbackEndpoint = $this->baseUrl . '/initiate.php';
                    $fallbackResponse = Http::timeout(30)
                        ->withHeaders([
                            'x-api-key' => $this->apiKey,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ])
                        ->post($fallbackEndpoint, $payload);

                    $fallbackData = $fallbackResponse->json();

                    if ($fallbackResponse->status() == 200 && (isset($fallbackData['reference_id']) || isset($fallbackData['data']['reference_id']))) {
                        $refId = $fallbackData['reference_id'] ?? $fallbackData['data']['reference_id'];
                        $order->update([
                            'reference_id' => $refId,
                            'api_status' => 'success'
                        ]);
                        Log::info('MTN order sent to CodeCraft via fallback', ['reference_id' => $refId]);
                    } else {
                        $order->update(['api_status' => 'failed']);
                        Log::error('CodeCraft MTN API fallback failed', ['response' => $fallbackData]);
                    }
                } else {
                    $message = $responseData['message'] ?? '';
                    if ($this->isUnverifiedNumberError($message)) {
                        $this->cancelAndRefund($order, $message);
                    } else {
                        $order->update(['api_status' => 'failed']);
                    }
                    Log::error('CodeCraft MTN API Error', [
                        'status_code' => $responseData['status'] ?? $response->status(),
                        'message' => $message,
                        'order_id' => $order->id
                    ]);
                }
            } catch (\Exception $e) {
                $order->update(['api_status' => 'failed']);
                Log::error('CodeCraft MTN API Exception', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage()
                ]);
            }
        }
    }

    private function isUnverifiedNumberError($message)
    {
        $keywords = ['not verified', 'unverified', 'invalid number', 'not registered', 'unregistered', 'number not found'];
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function cancelAndRefund(Order $order, $reason)
    {
        DB::transaction(function () use ($order, $reason) {
            $user = User::lockForUpdate()->find($order->user_id);
            $balanceBefore = $user->wallet_balance;
            $user->increment('wallet_balance', $order->total);
            $balanceAfter = $user->fresh()->wallet_balance;

            $order->update([
                'status' => 'cancelled',
                'api_status' => 'failed',
            ]);

            Transaction::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'amount' => $order->total,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'type' => 'refund',
                'description' => "Auto-refund for order #{$order->id}: {$reason}",
            ]);
        });

        Log::info('CodeCraft MTN: Order auto-cancelled and refunded due to unverified number', [
            'order_id' => $order->id,
            'amount' => $order->total,
            'reason' => $reason,
        ]);
    }

    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 9) {
            return '0' . $phone;
        }
        return $phone;
    }

    private function getNetworkFromProduct($productName)
    {
        if (stripos($productName, 'mtn') !== false) {
            return 'MTN';
        }
        return null;
    }
}
