<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderWebhookService
{
    public static function send(Order $order, string $previousStatus): void
    {
        $endpoints = WebhookEndpoint::where('user_id', $order->user_id)
            ->where('active', true)
            ->get();

        if ($endpoints->isEmpty()) {
            return;
        }

        $payload = [
            'event' => 'order.status_changed',
            'data' => [
                'order_id' => $order->id,
                'reference_id' => $order->reference_id,
                'beneficiary_number' => $order->beneficiary_number,
                'network' => $order->network,
                'previous_status' => $previousStatus,
                'new_status' => $order->status,
                'updated_at' => $order->updated_at->toIso8601String(),
            ],
        ];

        foreach ($endpoints as $endpoint) {
            $signature = hash_hmac('sha256', json_encode($payload), $endpoint->secret);

            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'X-Webhook-Signature' => $signature,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($endpoint->url, $payload);

                Log::info('Webhook sent', [
                    'order_id' => $order->id,
                    'endpoint' => $endpoint->url,
                    'status_code' => $response->status(),
                ]);
            } catch (\Exception $e) {
                Log::error('Webhook delivery failed', [
                    'order_id' => $order->id,
                    'endpoint' => $endpoint->url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
