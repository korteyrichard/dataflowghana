<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataworksOrderStatusSyncService
{
    private DataworksOrderPusherService $pusher;
    private MoolreSmsService $sms;

    public function __construct()
    {
        $this->pusher = new DataworksOrderPusherService();
        $this->sms    = new MoolreSmsService();
    }

    public function syncOrderStatuses(): void
    {
        if (!(bool) Setting::get('dataworks_order_pusher_enabled', 0)) {
            Log::info('Dataworks order sync skipped: pusher is disabled');
            return;
        }

        $orders = Order::whereIn('status', ['pending', 'processing'])
            ->whereNotNull('reference_id')
            ->where('network', 'like', '%mtn%')
            ->whereHas('products', fn ($query) => $query->where('name', 'like', '%mtn express%'))
            ->with('user')
            ->get();

        Log::info('Dataworks order sync started', ['orders_found' => $orders->count()]);

        foreach ($orders as $order) {
            try {
                $this->syncOrder($order);
            } catch (\Exception $e) {
                Log::error('Dataworks sync error', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function syncOrder(Order $order): void
    {
        $data = $this->pusher->checkOrderStatus($order->reference_id);

        Log::info('Dataworks status check response', [
            'order_id'     => $order->id,
            'reference_id' => $order->reference_id,
            'response'     => $data,
        ]);

        if (!$data || ($data['code'] ?? null) !== 200) {
            return;
        }

        $externalStatus = $data['data']['orders'][0]['order_status'] ?? null;

        if (!$externalStatus) {
            return;
        }

        $newStatus = $this->mapStatus($externalStatus);

        if (!$newStatus || $newStatus === $order->status) {
            return;
        }

        $order->update(['status' => $newStatus]);

        Log::info('Dataworks order status updated', [
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
                Log::error('Dataworks SMS failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
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
                'description'    => "Auto-refund for cancelled order #{$order->id} (Dataworks)",
            ]);
        });

        Log::info('Dataworks commission reversed', ['order_id' => $order->id]);
    }

    private function mapStatus(string $status): ?string
    {
        return [
            'pending'    => 'processing',
            'processing' => 'processing',
            'delivered'  => 'completed',
            'failed'     => 'cancelled',
            'cancelled'  => 'cancelled',
        ][strtolower($status)] ?? null;
    }
}
