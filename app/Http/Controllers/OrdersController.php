<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Services\OrderPusherService;
use App\Services\CodeCraftOrderPusherService;
use App\Models\Setting;

class OrdersController extends Controller
{
    // Display a listing of the user's orders
    public function index()
    {
        $orders = Order::with(['products' => function($query) {
            $query->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id');
        }])->where('user_id', Auth::id())->latest()->get();
        
        // Transform orders to include variant information
        $orders = $orders->map(function($order) {
            $order->products = $order->products->map(function($product) {
                if ($product->pivot->product_variant_id) {
                    $variant = \App\Models\ProductVariant::find($product->pivot->product_variant_id);
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
        $user = Auth::user();

        $cartItems = Cart::where('user_id', $user->id)->with(['product', 'productVariant'])->get();
        Log::info('Cart items fetched.', ['cartItemsCount' => $cartItems->count()]);

        if ($cartItems->isEmpty()) {
            Log::warning('Cart is empty for user.', ['userId' => $user->id]);
            return redirect()->back()->with('error', 'Cart is empty');
        }

        // Calculate total by summing the price of each cart item
        $total = $cartItems->sum(function ($item) {
            return (float) ($item->price ?? ($item->productVariant->price ?? 0));
        });
        Log::info('Total calculated.', ['total' => $total, 'walletBalance' => $user->wallet_balance]);

        // Check if user has enough wallet balance
        if ($user->wallet_balance < $total) {
            Log::warning('Insufficient wallet balance.', ['userId' => $user->id, 'walletBalance' => $user->wallet_balance, 'total' => $total]);
            return redirect()->back()->with('error', 'Insufficient wallet balance. Top up to proceed with the purchase.');
        }

        Log::info('Creating separate orders for each cart item.', ['cartItemsCount' => $cartItems->count()]);

        DB::beginTransaction();
        Log::info('Database transaction started.');
        try {
            // Deduct wallet balance (use bcsub for decimal math and cast to float for decimal:2)
            $user->wallet_balance = (float) bcsub((string) $user->wallet_balance, (string) $total, 2);
            $user->save();
            Log::info('Wallet balance deducted.', ['userId' => $user->id, 'newWalletBalance' => $user->wallet_balance]);

            $createdOrders = [];

            // Create separate order for each cart item
            foreach ($cartItems as $item) {
                $itemTotal = (float) ($item->price ?? ($item->productVariant->price ?? 0));
                $network = $item->product->network;

                // Create the order for this item
                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => strtolower($network) === 'ishare' ? 'completed' : 'pending',
                    'total' => $itemTotal,
                    'beneficiary_number' => $item->beneficiary_number,
                    'network' => $network,
                ]);
                Log::info('Order created for cart item.', ['orderId' => $order->id, 'network' => $network, 'total' => $itemTotal, 'beneficiaryNumber' => $item->beneficiary_number]);

                // Attach the product to the order
                $order->products()->attach($item->product_id, [
                    'quantity' => (int) ($item->quantity ?? 1),
                    'price' => $itemTotal,
                    'beneficiary_number' => $item->beneficiary_number,
                    'product_variant_id' => $item->product_variant_id,
                ]);
                Log::info('Product attached to order.', ['orderId' => $order->id, 'productId' => $item->product_id, 'beneficiaryNumber' => $item->beneficiary_number]);

                // Create a transaction record for this order
                \App\Models\Transaction::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'amount' => $itemTotal,
                    'status' => 'completed',
                    'type' => 'order',
                    'description' => 'Order placed for ' . $network . ' data/airtime.',
                ]);
                Log::info('Transaction created for order.', ['orderId' => $order->id, 'network' => $network]);

                $createdOrders[] = $order;
            }

            // Clear user's cart
            Cart::where('user_id', $user->id)->delete();
            Log::info('Cart cleared.', ['userId' => $user->id]);

            DB::commit();
            Log::info('Database transaction committed.');

            // Push orders to external APIs based on network and individual service settings
            $jaybartEnabled = (bool) Setting::get('jaybart_order_pusher_enabled', 1);
            $codecraftEnabled = (bool) Setting::get('codecraft_order_pusher_enabled', 1);
            
            foreach ($createdOrders as $order) {
                try {
                    if (strtolower($order->network) === 'mtn' && $jaybartEnabled) {
                        $mtnOrderPusher = new OrderPusherService();
                        $mtnOrderPusher->pushOrderToApi($order);
                        Log::info('Order pushed to Jaybart API', ['orderId' => $order->id, 'network' => $order->network]);
                    } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime']) && $codecraftEnabled) {
                        $codeCraftOrderPusher = new CodeCraftOrderPusherService();
                        $codeCraftOrderPusher->pushOrderToApi($order);
                        Log::info('Order pushed to CodeCraft API', ['orderId' => $order->id, 'network' => $order->network]);
                    } else {
                        $service = strtolower($order->network) === 'mtn' ? 'Jaybart' : 'CodeCraft';
                        $enabled = strtolower($order->network) === 'mtn' ? $jaybartEnabled : $codecraftEnabled;
                        Log::info('Order pusher disabled for service', ['orderId' => $order->id, 'network' => $order->network, 'service' => $service, 'enabled' => $enabled]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to push order to external API', ['orderId' => $order->id, 'network' => $order->network, 'error' => $e->getMessage()]);
                }
            }

            $orderCount = count($createdOrders);
            $successMessage = $orderCount === 1 
                ? 'Order placed successfully!' 
                : "$orderCount orders placed successfully!";

            return redirect()->route('dashboard.orders')->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed during transaction.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Checkout failed: ' . $e->getMessage());
        }
    }
}