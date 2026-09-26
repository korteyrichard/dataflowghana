<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BundlePortalOrderFailureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BundlePortalWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = (string) config('services.bundleportal.webhook_secret', '');

        if ($secret === '') {
            return response()->json(['message' => 'Bundle Portal webhook secret is not configured.'], 503);
        }

        $rawBody = $request->getContent();
        $signature = (string) $request->header('X-BundlePortal-Signature', '');
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        if ($signature === '' || !hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($rawBody, true);
        $eventStatuses = [
            'order.completed' => 'completed',
            'order.failed' => 'failed',
            'order.cancelled' => 'cancelled',
            'order.refunded' => 'refunded',
        ];

        if (!is_array($payload)
            || !isset($eventStatuses[$payload['event'] ?? ''])
            || strtolower((string) ($payload['status'] ?? '')) !== $eventStatuses[$payload['event']]) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        $externalOrderId = (string) ($payload['order_id'] ?? '');
        if (!preg_match('/^SD-[0-9]+$/', $externalOrderId)) {
            return response()->json(['message' => 'Unknown order.'], 404);
        }

        $order = Order::query()->where('reference_id', $externalOrderId)->first();
        if (!$order) {
            return response()->json(['message' => 'Unknown order.'], 404);
        }

        $status = strtolower($payload['status']);
        if ($status === 'completed') {
            DB::transaction(function () use ($order) {
                $lockedOrder = Order::query()->lockForUpdate()->find($order->id);

                if ($lockedOrder && !in_array($lockedOrder->status, ['completed', 'cancelled'], true)) {
                    $lockedOrder->update([
                        'status' => 'completed',
                        'api_status' => 'success',
                    ]);
                }
            });
        } else {
            BundlePortalOrderFailureService::refund(
                $order,
                (string) ($payload['failure_reason'] ?? $status)
            );
        }

        return response()->json(['success' => true]);
    }
}