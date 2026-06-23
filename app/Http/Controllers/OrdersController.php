<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Services\CodeCraftOrderPusherService;
use App\Services\CodeCraftMtnOrderPusherService;
use App\Services\MtnExpressOrderPusherService;
use App\Services\DataEasyOrderPusherService;
use App\Services\DataSourceOrderPusherService;
use App\Models\Setting;

class OrdersController extends Controller
{
    // Display a listing of the user's orders
    public function index(Request $request)
    {
        $query = Order::with([
            'products' => function($query) {
                $query->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')
                    ->select('products.id', 'products.name');
            }
        ])->where('user_id', auth()->id())
            ->select('id', 'user_id', 'status', 'total', 'beneficiary_number', 'network', 'api_status', 'created_at');
        
        if ($request->get('network')) {
            $query->where('network', $request->get('network'));
        }
        
        if ($request->get('status')) {
            $query->where('status', $request->get('status'));
        }
        
        if ($request->get('beneficiary')) {
            $beneficiary = $request->get('beneficiary');
            $query->where('beneficiary_number', 'like', '%' . $beneficiary . '%');
        }
        
        if ($request->get('order_id')) {
            $query->where('id', 'like', '%' . $request->get('order_id') . '%');
        }
        
        $orders = $query->latest()->paginate(50);
        
        $orders->getCollection()->transform(function($order) {
            $order->products = $order->products->map(function($product) {
                if ($product->pivot->product_variant_id) {
                    $variant = \App\Models\ProductVariant::select('variant_attributes')
                        ->find($product->pivot->product_variant_id);
                    if ($variant && isset($variant->variant_attributes['size'])) {
                        $product->size = strtoupper($variant->variant_attributes['size']);
                    }
                }
                return $product;
            });
            return $order;
        });

        return Inertia::render('Dashboard/orders', [
            'orders' => $orders
        ]);
    }

    // Handle checkout and create separate orders for each network
    public function checkout(Request $request)
    {
        Log::info('Checkout process started.');
        $user = auth()->user();
        $userId = $user->id;

        $cartItems = Cart::where('user_id', $userId)
            ->with(['product:id,network', 'productVariant:id,price'])
            ->select('id', 'product_id', 'product_variant_id', 'price', 'quantity', 'beneficiary_number')
            ->get();
        
        Log::info('Cart items fetched.', ['cartItemsCount' => $cartItems->count()]);

        if ($cartItems->isEmpty()) {
            Log::warning('Cart is empty for user.', ['userId' => $userId]);
            return redirect()->back()->with('error', 'Cart is empty');
        }

        $total = (float) $cartItems->sum(fn($item) => (float)($item->price ?? $item->productVariant?->price ?? 0));
        Log::info('Total calculated.', ['total' => $total, 'walletBalance' => $user->wallet_balance]);

        if ($user->wallet_balance < $total) {
            Log::warning('Insufficient wallet balance.', ['userId' => $userId, 'walletBalance' => $user->wallet_balance, 'total' => $total]);
            return redirect()->back()->with('error', 'Insufficient wallet balance. Top up to proceed with the purchase.');
        }

        DB::beginTransaction();
        try {
            // Lock user row and re-check balance to prevent race conditions
            $user = User::lockForUpdate()->find($userId);

            if ($user->wallet_balance < $total) {
                DB::rollBack();
                Log::warning('Insufficient wallet balance after lock.', ['userId' => $userId, 'walletBalance' => $user->wallet_balance, 'total' => $total]);
                return redirect()->back()->with('error', 'Insufficient wallet balance. Top up to proceed with the purchase.');
            }

            $balanceBefore = $user->wallet_balance;
            $newBalance = (float)bcsub((string)$user->wallet_balance, (string)$total, 2);

            if ($newBalance < 0) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Insufficient wallet balance. Top up to proceed with the purchase.');
            }
            
            User::where('id', $userId)->update(['wallet_balance' => $newBalance]);
            $user->wallet_balance = $newBalance;
            
            Log::info('Wallet balance deducted.', ['userId' => $userId, 'newBalance' => $newBalance]);

            $orderData = [];
            $transactionData = [];
            $pivotInserts = [];
            $itemsProcessed = 0;

            foreach ($cartItems as $item) {
                $itemTotal = (float)($item->price ?? $item->productVariant?->price ?? 0);
                $network = $item->product->network;
                
                $orderData[] = [
                    'user_id' => $userId,
                    'status' => strtolower($network) === 'ishare' ? 'completed' : 'pending',
                    'total' => $itemTotal,
                    'beneficiary_number' => $item->beneficiary_number,
                    'network' => $network,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            Order::insert($orderData);
            
            $createdOrders = Order::where('user_id', $userId)
                ->orderByDesc('created_at')
                ->limit($cartItems->count())
                ->get();
            
            Log::info('Orders batch inserted.', ['count' => count($createdOrders)]);

            foreach ($cartItems as $index => $item) {
                $itemTotal = (float)($item->price ?? $item->productVariant?->price ?? 0);
                $order = $createdOrders[$index];
                
                $pivotInserts[] = [
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => (int)($item->quantity ?? 1),
                    'price' => $itemTotal,
                    'beneficiary_number' => $item->beneficiary_number,
                    'product_variant_id' => $item->product_variant_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                $balanceBeforeItem = (float)bcsub((string)$balanceBefore, (string)$itemsProcessed, 2);
                $itemsProcessed = (float)bcadd((string)$itemsProcessed, (string)$itemTotal, 2);
                
                // Add milliseconds to each transaction to ensure proper ordering
                $transactionTime = now()->addMilliseconds($index * 10);
                
                $transactionData[] = [
                    'user_id' => $userId,
                    'order_id' => $order->id,
                    'amount' => $itemTotal,
                    'balance_before' => $balanceBeforeItem,
                    'balance_after' => (float)bcsub((string)$balanceBeforeItem, (string)$itemTotal, 2),
                    'status' => 'completed',
                    'type' => 'order',
                    'description' => 'Order placed for ' . $item->product->network . ' data/airtime.',
                    'created_at' => $transactionTime,
                    'updated_at' => $transactionTime,
                ];
            }

            if (!empty($pivotInserts)) {
                DB::table('order_product')->insert($pivotInserts);
                Log::info('Products batch attached.', ['count' => count($pivotInserts)]);
            }

            if (!empty($transactionData)) {
                \App\Models\Transaction::insert($transactionData);
                Log::info('Transactions batch inserted.', ['count' => count($transactionData)]);
            }

            Cart::where('user_id', $userId)->delete();
            Log::info('Cart cleared.', ['userId' => $userId]);

            DB::commit();
            Log::info('Database transaction committed.');

            $datamasterEnabled = (bool) Setting::get('datamaster_order_pusher_enabled', 1);
            $codecraftEnabled = (bool) Setting::get('codecraft_order_pusher_enabled', 1);
            $dataeasyEnabled = (bool) Setting::get('dataeasy_order_pusher_enabled', 0);
            $dataSourceEnabled = (bool) Setting::get('datasource_order_pusher_enabled', 1);
            $codecraftMtnEnabled = (bool) Setting::get('codecraft_mtn_order_pusher_enabled', 0);
            
            foreach ($createdOrders as $order) {
                try {
                    $isMtn = stripos($order->network, 'mtn') !== false;
                    $isMtnExpress = stripos($order->network, 'mtn express') !== false;
                    
                    if ($isMtnExpress && $datamasterEnabled) {
                        $mtnOrderPusher = new MtnExpressOrderPusherService();
                        $mtnOrderPusher->pushOrderToApi($order);
                        Log::info('Order pushed to DataMaster API', ['orderId' => $order->id]);
                    } elseif ($isMtn && !$isMtnExpress && $codecraftMtnEnabled) {
                        $codecraftMtnPusher = new CodeCraftMtnOrderPusherService();
                        $codecraftMtnPusher->pushOrderToApi($order);
                        Log::info('Order pushed to CodeCraft MTN API', ['orderId' => $order->id]);
                    } elseif ($isMtn && !$isMtnExpress && $dataSourceEnabled) {
                        $dataSourceOrderPusher = new DataSourceOrderPusherService();
                        $dataSourceOrderPusher->pushOrderToApi($order);
                        Log::info('Order pushed to DataSource API', ['orderId' => $order->id]);
                    } elseif ($isMtn && !$isMtnExpress && $dataeasyEnabled) {
                        $dataEasyOrderPusher = new DataEasyOrderPusherService();
                        $dataEasyOrderPusher->pushOrderToApi($order);
                        Log::info('Order pushed to DataEasy API', ['orderId' => $order->id]);
                    } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime']) && $codecraftEnabled) {
                        $codeCraftOrderPusher = new CodeCraftOrderPusherService();
                        $codeCraftOrderPusher->pushOrderToApi($order);
                        Log::info('Order pushed to CodeCraft API', ['orderId' => $order->id]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to push order', ['orderId' => $order->id, 'error' => $e->getMessage()]);
                }
            }

            $orderCount = count($createdOrders);
            $successMessage = $orderCount === 1 
                ? 'Order placed successfully!' 
                : "{$orderCount} orders placed successfully!";

            return redirect()->route('dashboard.orders')->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed.', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Checkout failed: ' . $e->getMessage());
        }
    }
}
