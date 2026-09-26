<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BundlePortalOrderFailureService
{
    public static function refund(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);

            if (!$lockedOrder || in_array($lockedOrder->status, ['completed', 'cancelled'], true)) {
                return;
            }

            $user = User::query()->lockForUpdate()->find($lockedOrder->user_id);

            if (!$user) {
                return;
            }

            $balanceBefore = (float) $user->wallet_balance;
            $user->increment('wallet_balance', $lockedOrder->total);
            $balanceAfter = (float) $user->fresh()->wallet_balance;

            $lockedOrder->update([
                'status' => 'cancelled',
                'api_status' => 'failed',
            ]);

            Transaction::create([
                'user_id' => $lockedOrder->user_id,
                'order_id' => $lockedOrder->id,
                'amount' => $lockedOrder->total,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'type' => 'refund',
                'description' => "Auto-refund for order #{$lockedOrder->id} (BundlePortal): {$reason}",
            ]);
        });

        Log::info('BundlePortal order refunded', [
            'order_id' => $order->id,
            'reason' => $reason,
        ]);
    }
}