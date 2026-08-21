<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BundlePortalMtnOrderStatusSyncService
{
    private BundlePortalMtnOrderPusherService $pusher;
    private MoolreSmsService $sms;

    public function __construct()
    {
        $this->pusher = new BundlePortalMtnOrderPusherService();
        $this->sms    = new MoolreSmsService();
    }

    public function syncOrderStatuses(): void
    {
        if (!(bool) Setting::get('bundleportal_mtn_api_enabled', 0)) {
            Log::info('BundlePortal MTN order sync skipped: pusher is disabled');
            return;
        }

        $orders = Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->where('reference_id', 'like', 'SD-%')
            ->where('network', 'like', '%mtn%')
            ->with('user')
            ->get();

        Log::info('BundlePortal MTN order sync started', ['orders_found' => $orders->count()]);

        foreach ($orders as $order) {
            try {
                $this->syncOrder($order);
            } catch (\Exception $e) {
                Log::error('BundlePortal MTN sync error', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function syncOrder(Order $order): void
    {
        $data = $this->pusher->checkOrderStatus($order->reference_id);

        Log::info('BundlePortal MTN status check response', [
            'order_id'     => $order->id,
            'reference_id' => $order->reference_id,
            'response'     => $data,
        ]);

        if (!$data || !isset($data['data']['status'])) {
            return;
        }

        $newStatus = $this->mapStatus($data['data']['status']);

        if (!$newStatus || $newStatus === $order->status) {
            return;
        }

        $order->update(['status' => $newStatus]);

        Log::info('BundlePortal MTN status updated', [
            'order_id'   => $order->id,
            'new_status' => $newStatus,
        ]);

        if ($newStatus === 'completed') {
            $this->sendCompletionSms($order);
        } elseif ($newStatus === 'cancelled') {
            $this->reverseCommission($order);
        }
    }

    private function sendCompletionSms(Order $order): void
    {
        if ($order->user && $order->user->phone) {
            try {
                $this->sms->sendSms(
                    $order->user->phone,
                    "Your order #{$order->id} for {$order->network} data has been completed successfully. Thank you!"
                );
            } catch (\Exception $e) {
                Log::error('BundlePortal MTN SMS failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function reverseCommission(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $user = User::lockForUpdate()->find($order->user_id);
            if (!$user) return;

            $balanceBefore = $user->wallet_balance;
            $user->increment('wallet_balance', $order->total);

            Transaction::create([
                'user_id'        => $order->user_id,
                'order_id'       => $order->id,
                'amount'         => $order->total,
                'balance_before' => $balanceBefore,
                'balance_after'  => $user->fresh()->wallet_balance,
                'status'         => 'completed',
                'type'           => 'refund',
                'description'    => "Auto-refund for cancelled order #{$order->id} (BundlePortal MTN)",
            ]);
        });

        Log::info('BundlePortal MTN commission reversed', ['order_id' => $order->id]);
    }

    private function mapStatus(string $status): ?string
    {
        return [
            'processing' => 'processing',
            'pending'    => 'processing',
            'cached'     => 'processing',
            'completed'  => 'completed',
            'delivered'  => 'completed',
            'successful' => 'completed',
            'failed'     => 'cancelled',
            'cancelled'  => 'cancelled',
        ][strtolower($status)] ?? null;
    }
}
