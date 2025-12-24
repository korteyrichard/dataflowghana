<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use App\Services\OrderPusherService;
use App\Services\CodeCraftOrderPusherService;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = auth()->user()->orders()->with('products')->latest()->get();
        return response()->json($orders);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'beneficiary_number' => 'required|string',
            'network_id' => 'required|integer',
            'size' => 'required|string'
        ]);

        $user = auth()->user();
        
        // Map network IDs to network names
        $networkMap = [
            5 => 'MTN',      // Agent MTN
            6 => 'TELECEL',  // Agent Telecel
            7 => 'ISHARE',   // Agent Ishare
            8 => 'BIGTIME',  // Agent Bigtime
            9 => 'MTN',      // Dealer MTN
            10 => 'TELECEL', // Dealer Telecel
            11 => 'ISHARE',  // Dealer Ishare
            12 => 'BIGTIME', // Dealer Bigtime
            13 => 'MTN',     // Elite MTN
            14 => 'TELECEL', // Elite Telecel
            15 => 'ISHARE',  // Elite Ishare
            16 => 'BIGTIME', // Elite Bigtime
        ];
        
        if (!isset($networkMap[$request->network_id])) {
            return response()->json(['error' => 'Invalid network ID'], 400);
        }
        
        $networkName = $networkMap[$request->network_id];
        
        // Determine product type based on network_id range
        if (in_array($request->network_id, [5, 6, 7, 8])) {
            $productType = 'agent_product';
        } elseif (in_array($request->network_id, [13, 14, 15, 16])) {
            $productType = 'elite_product';
        } else {
            $productType = 'dealer_product';
        }
        
        $product = Product::where('network', $networkName)
            ->where('product_type', $productType)
            ->first();
            
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $variant = ProductVariant::where('product_id', $product->id)
            ->whereJsonContains('variant_attributes->size', $request->size)
            ->first();
            
        if (!$variant) {
            return response()->json(['error' => 'Size variant not available'], 404);
        }

        if (auth()->user()->wallet_balance < $variant->price) {
            return response()->json(['error' => 'Insufficient wallet balance'], 400);
        }

        $order = DB::transaction(function() use ($request, $product, $variant) {
            auth()->user()->decrement('wallet_balance', $variant->price);
            
            $order = Order::create([
                'user_id' => auth()->id(),
                'total' => $variant->price,
                'beneficiary_number' => $request->beneficiary_number,
                'network' => $product->network,
                'status' => 'pending'
            ]);

            $order->products()->attach($product->id, [
                'quantity' => 1,
                'price' => $variant->price,
                'beneficiary_number' => $request->beneficiary_number,
                'product_variant_id' => $variant->id
            ]);

            return $order;
        });
        
        // Push order to external API based on network (if enabled)
        try {
            if (strtolower($order->network) === 'mtn' && Setting::get('jaybart_order_pusher_enabled', 1)) {
                $mtnOrderPusher = new OrderPusherService();
                $mtnOrderPusher->pushOrderToApi($order);
            } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime']) && Setting::get('codecraft_order_pusher_enabled', 1)) {
                $codeCraftOrderPusher = new CodeCraftOrderPusherService();
                $codeCraftOrderPusher->pushOrderToApi($order);
            } else {
                // Order pusher is disabled for this network
                $order->update(['api_status' => 'disabled']);
                Log::info('Order pusher disabled for network - skipping API call', ['orderId' => $order->id, 'network' => $order->network]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to push order to external API', ['orderId' => $order->id, 'network' => $order->network, 'error' => $e->getMessage()]);
        }

        // Load the order with its products and user for the response
        $order->load(['products' => function($query) {
            $query->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id');
        }, 'user']);
        
        return response()->json([
            'message' => 'Order created successfully',
            'order' => [
                'reference_id' => $order->id,
                'total' => $order->total,
                'status' => $order->status,
                'network' => $order->network,
                'beneficiary_number' => $order->beneficiary_number,
                'created_at' => $order->created_at,
                'user' => [
                    'name' => $order->user->name,
                    'email' => $order->user->email
                ],
                'products' => $order->products->map(function($product) {
                    return [
                        'name' => $product->name,
                        'quantity' => $product->pivot->quantity,
                        'price' => $product->pivot->price
                    ];
                })
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
