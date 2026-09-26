<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundlePortalWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_invalid_signatures(): void
    {
        config(['services.bundleportal.webhook_secret' => 'test-secret']);

        $response = $this->call(
            'POST',
            '/api/bundleportal/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BUNDLEPORTAL_SIGNATURE' => 'sha256=invalid'],
            json_encode(['event' => 'order.completed', 'status' => 'completed', 'order_id' => 'SD-1'])
        );

        $response->assertUnauthorized();
    }

    public function test_signed_completion_updates_the_order(): void
    {
        config(['services.bundleportal.webhook_secret' => 'test-secret']);
        $order = $this->createOrder();

        $this->sendWebhook([
            'event' => 'order.completed',
            'order_id' => $order->reference_id,
            'status' => 'completed',
        ])->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
            'api_status' => 'success',
        ]);
    }

    public function test_repeated_failure_callbacks_refund_only_once(): void
    {
        config(['services.bundleportal.webhook_secret' => 'test-secret']);
        $order = $this->createOrder();
        $payload = [
            'event' => 'order.failed',
            'order_id' => $order->reference_id,
            'status' => 'failed',
            'failure_reason' => 'Provider rejected the order',
        ];

        $this->sendWebhook($payload)->assertOk();
        $this->sendWebhook($payload)->assertOk();

        $this->assertEquals(100.0, (float) $order->user->fresh()->wallet_balance);
        $this->assertEquals(1, Transaction::where('order_id', $order->id)->where('type', 'refund')->count());
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'api_status' => 'failed',
        ]);
    }

    private function createOrder(): Order
    {
        $user = User::factory()->create(['wallet_balance' => 80]);

        return Order::create([
            'user_id' => $user->id,
            'total' => 20,
            'status' => 'pending',
            'network' => 'telecel',
            'reference_id' => 'SD-1',
            'api_status' => 'success',
        ]);
    }

    private function sendWebhook(array $payload)
    {
        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'test-secret');

        return $this->call(
            'POST',
            '/api/bundleportal/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BUNDLEPORTAL_SIGNATURE' => $signature],
            $body
        );
    }
}