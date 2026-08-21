<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\DataSourceOrderPusherService;
use App\Services\CodeCraftOrderPusherService;
use App\Services\CodeCraftMtnOrderPusherService;
use App\Services\DataMastaOrderPusherService;
use App\Services\RexamartOrderPusherService;
use App\Services\CodeCraftTelecelOrderPusherService;
use App\Services\BundlePortalMtnOrderPusherService;
use App\Services\BundlePortalOrderPusherService;
use App\Services\DataEasyOrderPusherService;
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
            $balanceTracker = $user->wallet_balance; // capture before deduction for transaction records
            $user->wallet_balance = $newBalance;
            $user->save();

            // Create orders
            $createdOrders = [];
            $transactionData = [];
            
            foreach ($orders as $index => $orderData) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'total' => $orderData['price'],
                    'beneficiary_number' => $orderData['phone'],
                    'network' => $request->network,
                ]);

                // Attach product to order
                $variantId = $orderData['product_variant_id'] ?? null;
                $product = $variantId
                    ? \App\Models\ProductVariant::find($variantId)?->product
                    : Product::where('network', $request->network)->first();
                if ($product) {
                    $order->products()->attach($product->id, [
                        'quantity' => 1,
                        'price' => $orderData['price'],
                        'beneficiary_number' => $orderData['phone'],
                        'product_variant_id' => $variantId,
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
                $createdOrders[] = $order->load('products');
            }
            
            // Batch insert all transactions at once
            \App\Models\Transaction::insert($transactionData);

            DB::commit();

            $bundlePortalMtnEnabled = (bool) Setting::get('bundleportal_mtn_api_enabled', 0);
            $bundlePortalEnabled = (bool) Setting::get('bundleportal_api_enabled', 0);
            $codecraftMtnEnabled = (bool) Setting::get('codecraft_mtn_order_pusher_enabled', 0);
            $dataMastaEnabled = (bool) Setting::get('datamasta_order_pusher_enabled', 0);
            $dataSourceEnabled = (bool) Setting::get('datasource_order_pusher_enabled', 1);
            $dataeasyEnabled = (bool) Setting::get('dataeasy_order_pusher_enabled', 0);
            $codeCraftTelecelEnabled = (bool) Setting::get('codecraft_telecel_order_pusher_enabled', 0);
            $rexamartEnabled = (bool) Setting::get('rexamart_order_pusher_enabled', 0);
            $codecraftEnabled = (bool) Setting::get('codecraft_order_pusher_enabled', 1);

            foreach ($createdOrders as $order) {
                try {
                    $isMtnExpress = strtoupper(trim($order->network)) === 'MTN EXPRESS';
                    $isMtn = !$isMtnExpress && stripos($order->network, 'mtn') !== false;
                    $isTelecelOrAt = in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime', 'airteltigo']);

                    if ($isMtnExpress && $bundlePortalMtnEnabled) {
                        (new BundlePortalMtnOrderPusherService())->pushOrderToApi($order);
                        Log::info('Bulk order pushed to BundlePortal MTN', ['order_id' => $order->id]);
                    } elseif ($isMtnExpress && $codecraftMtnEnabled) {
                        (new CodeCraftMtnOrderPusherService())->pushOrderToApi($order);
                        Log::info('Bulk order pushed to CodeCraft MTN', ['order_id' => $order->id]);
                    } elseif ($isMtnExpress) {
                        $order->update(['api_status' => 'disabled']);
                    } elseif ($isMtn && !$isMtnExpress && $dataMastaEnabled) {
                        (new DataMastaOrderPusherService())->pushOrderToApi($order);
                        Log::info('Bulk order pushed to DataMasta', ['order_id' => $order->id]);
                    } elseif ($isMtn && !$isMtnExpress && $dataSourceEnabled) {
                        (new DataSourceOrderPusherService())->pushOrderToApi($order);
                        Log::info('Bulk order pushed to DataSource', ['order_id' => $order->id]);
                    } elseif ($isMtn && !$isMtnExpress && $dataeasyEnabled) {
                        (new DataEasyOrderPusherService())->pushOrderToApi($order);
                        Log::info('Bulk order pushed to DataEasy', ['order_id' => $order->id]);
                    } elseif ($isMtn) {
                        $order->update(['api_status' => 'disabled']);
                        Log::warning('No MTN pusher enabled for bulk order', ['order_id' => $order->id]);
                    } elseif ($isTelecelOrAt && $bundlePortalEnabled) {
                        (new BundlePortalOrderPusherService())->pushOrderToApi($order);
                        Log::info('Bulk order pushed to BundlePortal (Telecel/AT)', ['order_id' => $order->id]);
                    } elseif (strtolower($order->network) === 'telecel' && $codeCraftTelecelEnabled) {
                        (new CodeCraftTelecelOrderPusherService())->pushOrderToApi($order);
                        Log::info('Bulk order pushed to CodeCraft Telecel', ['order_id' => $order->id]);
                    } elseif (strtolower($order->network) === 'telecel' && $rexamartEnabled) {
                        (new RexamartOrderPusherService())->pushOrderToApi($order);
                        Log::info('Bulk order pushed to Rexamart', ['order_id' => $order->id]);
                    } elseif (in_array(strtolower($order->network), ['ishare', 'bigtime']) && $codecraftEnabled) {
                        (new CodeCraftOrderPusherService())->pushOrderToApi($order);
                        Log::info('Bulk order pushed to CodeCraft (AT)', ['order_id' => $order->id]);
                    } else {
                        $order->update(['api_status' => 'disabled']);
                        Log::warning('No pusher enabled for bulk order', ['order_id' => $order->id, 'network' => $order->network]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to push bulk order', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
                usleep(300000);
            }

            return redirect()->route('dashboard.orders')->with('success', 'Bulk order placed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk order failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Order failed: ' . $e->getMessage());
        }
    }
}