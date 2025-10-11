<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\MoolreSmsService;

class CompleteOldOrders extends Command
{
    protected $signature = 'orders:complete-old';
    protected $description = 'Complete orders that are pending/processing for bigtime and telecel networks after 20 minutes';

    public function handle()
    {
        $twentyMinutesAgo = Carbon::now()->subMinutes(20);
        
        $orders = Order::whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<=', $twentyMinutesAgo)
            ->whereHas('products', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%bigtime%')
                      ->orWhere('name', 'like', '%telecel%');
                });
            })
            ->get();

        $smsService = new MoolreSmsService();
        
        foreach ($orders as $order) {
            $order->update(['status' => 'completed']);
            
            // Send SMS notification if user has phone
            if ($order->user && $order->user->phone) {
                $firstProduct = $order->products->first();
                $beneficiaryNumber = $firstProduct->pivot->beneficiary_number ?? 'N/A';
                $message = "Your order #{$order->id} for {$firstProduct->name} to {$beneficiaryNumber} has been completed. Total: GHS " . number_format($order->total, 2);
                $smsService->sendSms($order->user->phone, $message);
            }
            
            Log::info('Auto-completed old order', ['order_id' => $order->id]);
        }

        $this->info("Completed {$orders->count()} old orders");
        Log::info('Completed old orders command finished', ['orders_updated' => $orders->count()]);
    }
}