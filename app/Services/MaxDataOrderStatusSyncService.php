<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaxDataOrderStatusSyncService
{
    public function __construct(private MaxDataOrderPusherService $pusher) {}

    public function syncOrderStatuses(): void
    {
        if (!(bool) Setting::get('maxdata_order_pusher_enabled', 0)) {
            return;
        }

        Order::whereIn('status', ['pending', 'processing'])
            ->where('reference_id', 'like', 'MAXDATA:%')
            ->with('user')
            ->chunkById(50, function ($orders) {
                foreach ($orders as $order) {
                    try {
                        $id = substr((string) $order->reference_id, strlen('MAXDATA:'));
                        $response = $this->pusher->checkPurchase($id);
                        $purchase = $response['data'] ?? [];
                        $status = strtolower((string) ($purchase['status'] ?? ''));
                        $newStatus = match ($status) {
                            'paid', 'manual', 'submitted', 'uncertain' => 'processing',
                            'delivered' => 'completed',
                            'failed', 'refunded' => 'cancelled',
                            default => null,
                        };

                        if (!$newStatus || $newStatus === $order->status) {
                            continue;
                        }

                        if ($newStatus === 'cancelled') {
                            $this->refundOrder($order, $status);
                        } else {
                            $order->update(['status' => $newStatus]);
                            if ($newStatus === 'completed' && $order->user?->phone) {
                                try {
                                    (new MoolreSmsService())->sendSms(
                                        $order->user->phone,
                                        "Your MTN Express order #{$order->id} has been delivered successfully. Thank you!"
                                    );
                                } catch (\Throwable $e) {
                                    Log::error('Max Data completion SMS failed', [
                                        'order_id' => $order->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::error('Max Data order status sync failed', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    private function refundOrder(Order $order, string $externalStatus): void
    {
        DB::transaction(function () use ($order, $externalStatus) {
            $lockedOrder = Order::lockForUpdate()->find($order->id);
            if (!$lockedOrder || $lockedOrder->status === 'cancelled') {
                return;
            }

            $user = User::lockForUpdate()->find($lockedOrder->user_id);
            if (!$user) {
                return;
            }

            $before = $user->wallet_balance;
            $user->increment('wallet_balance', $lockedOrder->total);
            $lockedOrder->update(['status' => 'cancelled']);

            Transaction::create([
                'user_id' => $lockedOrder->user_id,
                'order_id' => $lockedOrder->id,
                'amount' => $lockedOrder->total,
                'balance_before' => $before,
                'balance_after' => $user->fresh()->wallet_balance,
                'status' => 'completed',
                'type' => 'refund',
                'description' => "Auto-refund for cancelled order #{$lockedOrder->id} (Max Data: {$externalStatus})",
            ]);
        });
    }
}
