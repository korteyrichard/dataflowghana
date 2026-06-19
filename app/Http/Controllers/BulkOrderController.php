<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\DataSourceOrderPusherService;
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
            // Deduct wallet balance
            $newBalance = (float)bcsub((string)$user->wallet_balance, (string)$total, 2);
            User::where('id', $user->id)->update(['wallet_balance' => $newBalance]);

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
            
            Log::info('Bulk order service settings check', [
                'network' => $request->network,
                'datamaster_enabled' => $datamasterEnabled,
                'codecraft_enabled' => $codecraftEnabled,
                'dataeasy_enabled' => $dataeasyEnabled,
                'datasource_enabled' => $dataSourceEnabled
            ]);
            
            // Push orders to appropriate APIs based on network and settings
            foreach ($createdOrders as $order) {
                try {
                    $isMtn = stripos($order->network, 'mtn') !== false;
                    $isMtnExpress = stripos($order->network, 'mtn express') !== false;
                    
                    if ($isMtnExpress && $datamasterEnabled) {
                        // MTN Express goes to DataMaster (single order API)
                        Log::info('MTN Express bulk orders not supported via DataMaster, processing individually');
                        // Note: DataMaster doesn't have bulk API, would need individual calls
                    } elseif ($isMtn && !$isMtnExpress && $dataSourceEnabled) {
                        // Regular MTN goes to DataSource (bulk API)
                        Log::info('Processing MTN bulk order via DataSource API', ['order_id' => $order->id]);
                    } elseif ($isMtn && !$isMtnExpress && $dataeasyEnabled) {
                        // Alternative MTN service (DataEasy)
                        Log::info('DataEasy does not support bulk API, skipping bulk order', ['order_id' => $order->id]);
                    } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime']) && $codecraftEnabled) {
                        // Other networks go to CodeCraft
                        Log::info('CodeCraft does not support bulk API, skipping bulk order', ['order_id' => $order->id]);
                    } else {
                        Log::info('No API service enabled or network not supported for bulk orders', [
                            'network' => $order->network,
                            'isMtn' => $isMtn,
                            'isMtnExpress' => $isMtnExpress
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to process bulk order for API', ['orderId' => $order->id, 'error' => $e->getMessage()]);
                }
            }
            
            // Actually push to DataSource bulk API only for regular MTN orders
            $mtnOrders = collect($createdOrders)->filter(function($order) {
                $isMtn = stripos($order->network, 'mtn') !== false;
                $isMtnExpress = stripos($order->network, 'mtn express') !== false;
                return $isMtn && !$isMtnExpress;
            });
            
            Log::info('Bulk API decision logic', [
                'total_orders' => count($createdOrders),
                'mtn_orders_count' => $mtnOrders->count(),
                'datasource_enabled' => $dataSourceEnabled,
                'network' => $request->network,
                'filtered_orders' => $mtnOrders->pluck('id', 'network')
            ]);
            
            if ($mtnOrders->count() > 0 && $dataSourceEnabled) {
                Log::info('Pushing to DataSource bulk API', [
                    'order_count' => $mtnOrders->count(),
                    'order_ids' => $mtnOrders->pluck('id')
                ]);
                $dataSourceOrderPusher = new DataSourceOrderPusherService();
                $dataSourceOrderPusher->pushBulkOrderToApi($mtnOrders->toArray(), $orders);
                Log::info('Bulk orders pushed to DataSource API', ['order_count' => $mtnOrders->count()]);
            } elseif (!$dataSourceEnabled) {
                // Set api_status to 'disabled' for all MTN orders when service is disabled
                foreach ($mtnOrders as $order) {
                    $order->update(['api_status' => 'disabled']);
                }
                Log::warning('DataSource order pusher is DISABLED in settings, skipping bulk API call', [
                    'datasource_enabled' => $dataSourceEnabled,
                    'setting_value' => Setting::get('datasource_order_pusher_enabled'),
                    'note' => 'Orders created successfully but not sent to external API',
                    'orders_marked_disabled' => $mtnOrders->count()
                ]);
            } elseif ($mtnOrders->count() === 0) {
                // Set api_status to 'not_applicable' for non-MTN networks
                foreach ($createdOrders as $order) {
                    $order->update(['api_status' => 'not_applicable']);
                }
                Log::warning('No regular MTN orders found for bulk API', [
                    'network' => $request->network,
                    'total_orders' => count($createdOrders),
                    'all_order_networks' => collect($createdOrders)->pluck('network')
                ]);
            }

            return redirect()->route('dashboard.orders')->with('success', 'Bulk order placed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk order failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Order failed: ' . $e->getMessage());
        }
    }
}