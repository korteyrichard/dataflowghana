<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\DataSourceOrderPusherService;
use App\Services\CodeCraftMtnOrderPusherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BulkOrderController extends Controller
{
    public function preview(Request $request)
    {
        // Handle GET requests (redirect to dashboard if no data)
        if ($request->isMethod('GET')) {
            return redirect()->route('dashboard')->with('info', 'Please select products to preview bulk orders.');
        }
        
        $request->validate([
            'orders' => 'required|array',
            'orders.*.phone' => 'required|string',
            'orders.*.bundle_size' => 'required|string', 
            'orders.*.price' => 'required|numeric',
            'network' => 'required|string'
        ]);

        return Inertia::render('Dashboard/BulkOrderPreview', [
            'orders' => $request->orders,
            'network' => $request->network,
            'total' => collect($request->orders)->sum('price')
        ]);
    }

    public function processBulk(Request $request)
    {
        $request->validate([
            'orders' => 'required|array',
            'network' => 'required|string'
        ]);

        $user = Auth::user();
        $orders = $request->orders;
        $total = collect($orders)->sum('price');

        if ($user->wallet_balance < $total) {
            return redirect()->back()->with('error', 'Insufficient wallet balance.');
        }

        DB::beginTransaction();
        try {
            // Lock user row and re-check balance to prevent race conditions
            $user = User::lockForUpdate()->find($user->id);

            if ($user->wallet_balance < $total) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Insufficient wallet balance.');
            }

            // Deduct wallet balance
            $newBalance = (float)bcsub((string)$user->wallet_balance, (string)$total, 2);
            if ($newBalance < 0) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Insufficient wallet balance.');
            }
            $user->update(['wallet_balance' => $newBalance]);

            // Create orders
            $createdOrders = [];
            $transactionData = [];
            $balanceTracker = $user->wallet_balance;
            
            foreach ($orders as $index => $orderData) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'total' => $orderData['price'],
                    'beneficiary_number' => $orderData['phone'],
                    'network' => $request->network,
                ]);

                // Attach product to order
                $product = Product::where('network', $request->network)->first();
                if ($product) {
                    $order->products()->attach($product->id, [
                        'quantity' => 1,
                        'price' => $orderData['price'],
                        'beneficiary_number' => $orderData['phone'],
                        'product_variant_id' => $orderData['product_variant_id'] ?? null,
                    ]);
                }

                // Prepare transaction data for batch insert
                $balanceAfter = (float)bcsub((string)$balanceTracker, (string)$orderData['price'], 2);
                
                $transactionData[] = [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'amount' => $orderData['price'],
                    'balance_before' => $balanceTracker,
                    'balance_after' => $balanceAfter,
                    'status' => 'completed',
                    'type' => 'bulk_order',
                    'description' => 'Bulk order for ' . $request->network . ' - ' . $orderData['phone'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                $balanceTracker = $balanceAfter;
                $createdOrders[] = $order;
            }
            
            // Batch insert all transactions at once
            \App\Models\Transaction::insert($transactionData);

            DB::commit();

            // Check service settings
            $datamasterEnabled = (bool) Setting::get('datamaster_order_pusher_enabled', 1);
            $codecraftEnabled = (bool) Setting::get('codecraft_order_pusher_enabled', 1);
            $dataeasyEnabled = (bool) Setting::get('dataeasy_order_pusher_enabled', 0);
            $dataSourceEnabled = (bool) Setting::get('datasource_order_pusher_enabled', 1);
            $codecraftMtnEnabled = (bool) Setting::get('codecraft_mtn_order_pusher_enabled', 0);
            
            // Filter regular MTN orders
            $mtnOrders = collect($createdOrders)->filter(function($order) {
                $isMtn = stripos($order->network, 'mtn') !== false;
                $isMtnExpress = stripos($order->network, 'mtn express') !== false;
                return $isMtn && !$isMtnExpress;
            });
            
            if ($mtnOrders->count() > 0 && $codecraftMtnEnabled) {
                // Push MTN bulk orders one by one through CodeCraft
                Log::info('Pushing MTN bulk orders to CodeCraft one by one', ['order_count' => $mtnOrders->count()]);
                $codecraftMtnPusher = new CodeCraftMtnOrderPusherService();
                foreach ($mtnOrders as $order) {
                    try {
                        $codecraftMtnPusher->pushOrderToApi($order);
                        Log::info('Bulk order pushed to CodeCraft MTN', ['order_id' => $order->id]);
                        usleep(500000); // 500ms delay between requests to avoid rate limiting
                    } catch (\Exception $e) {
                        Log::error('Failed to push bulk order to CodeCraft MTN', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                    }
                }
            } elseif ($mtnOrders->count() > 0 && $dataSourceEnabled) {
                Log::info('Pushing to DataSource bulk API', ['order_count' => $mtnOrders->count()]);
                $dataSourceOrderPusher = new DataSourceOrderPusherService();
                $dataSourceOrderPusher->pushBulkOrderToApi($mtnOrders->toArray(), $orders);
                Log::info('Bulk orders pushed to DataSource API', ['order_count' => $mtnOrders->count()]);
            } elseif ($mtnOrders->count() > 0) {
                foreach ($mtnOrders as $order) {
                    $order->update(['api_status' => 'disabled']);
                }
                Log::warning('No MTN order pusher enabled for bulk orders', ['order_count' => $mtnOrders->count()]);
            }
            
            // Handle non-MTN orders
            $nonMtnOrders = collect($createdOrders)->filter(function($order) {
                return stripos($order->network, 'mtn') === false;
            });
            
            if ($nonMtnOrders->count() > 0) {
                foreach ($nonMtnOrders as $order) {
                    $order->update(['api_status' => 'not_applicable']);
                }
            }

            return redirect()->route('dashboard.orders')->with('success', 'Bulk order placed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk order failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Order failed: ' . $e->getMessage());
        }
    }
}