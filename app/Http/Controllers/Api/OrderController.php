<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CodeCraftOrderPusherService;
use App\Services\MtnExpressOrderPusherService;
use App\Services\DataEasyOrderPusherService;
use App\Services\DataSourceOrderPusherService;
use App\Models\Transaction;
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

        $existingOrder = Order::where('beneficiary_number', $request->beneficiary_number)
            ->whereIn('status', ['pending', 'processing'])
            ->first();
        
        if ($existingOrder) {
            return response()->json([
                'error' => 'An order with this beneficiary number already exists with pending or processing status'
            ], 409);
        }

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
        
        // Handle MTN EXPRESS as a special case
        if ($networkName === 'MTN' && $request->has('is_express') && $request->is_express) {
            $networkName = 'MTN EXPRESS';
        }
        
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
            ->where('variant_attributes->size', '=', $request->size)
            ->first();
            
        if (!$variant) {
            return response()->json(['error' => 'Size variant not available'], 404);
        }

        if (auth()->user()->wallet_balance < $variant->price) {
            return response()->json(['error' => 'Insufficient wallet balance'], 400);
        }

        $order = DB::transaction(function() use ($request, $product, $variant) {
            $user = auth()->user();
            // Lock the user row to prevent race conditions
            $user = User::lockForUpdate()->find(auth()->id());
            
            $balanceBefore = $user->wallet_balance;
            if ($balanceBefore < $variant->price) {
                throw new \Exception('Insufficient wallet balance');
            }
            $user->decrement('wallet_balance', $variant->price);
            $balanceAfter = $user->fresh()->wallet_balance;
            
            $order = Order::create([
                'user_id' => auth()->id(),
                'total' => $variant->price,
                'beneficiary_number' => $request->beneficiary_number,
                'network' => $product->network,
                'status' => 'pending',
                'is_api_order' => true
            ]);

            $order->products()->attach($product->id, [
                'quantity' => 1,
                'price' => $variant->price,
                'beneficiary_number' => $request->beneficiary_number,
                'product_variant_id' => $variant->id
            ]);
            
            // Create transaction record for API order
            Transaction::create([
                'user_id' => auth()->id(),
                'order_id' => $order->id,
                'amount' => $variant->price,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'type' => 'order',
                'description' => 'Order placed for ' . $product->network . ' data/airtime.',
            ]);
            
            Log::info('Order processed successfully', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'amount' => $variant->price,
                'network' => $order->network
            ]);

            return $order;
        });
        
        // Push order to external API based on network (if enabled)
        try {
            $isMtn = stripos($order->network, 'mtn') !== false;
            $isMtnExpress = stripos($order->network, 'mtn express') !== false;
            $datamasterEnabled = (bool) Setting::get('datamaster_order_pusher_enabled', 1);
            $codecraftEnabled = (bool) Setting::get('codecraft_order_pusher_enabled', 1);
            $dataeasyEnabled = (bool) Setting::get('dataeasy_order_pusher_enabled', 0);
            $dataSourceEnabled = (bool) Setting::get('datasource_order_pusher_enabled', 1);
            
            if ($isMtnExpress && $datamasterEnabled) {
                $mtnOrderPusher = new MtnExpressOrderPusherService();
                $mtnOrderPusher->pushOrderToApi($order);
                Log::info('API Order pushed to DataMaster API (MTN Express)', ['orderId' => $order->id, 'network' => $order->network]);
            } elseif ($isMtn && !$isMtnExpress && $dataSourceEnabled) {
                $dataSourceOrderPusher = new DataSourceOrderPusherService();
                $dataSourceOrderPusher->pushOrderToApi($order);
                Log::info('API Order pushed to DataSource Order Pusher API', ['orderId' => $order->id, 'network' => $order->network]);
            } elseif ($isMtn && !$isMtnExpress && $dataeasyEnabled) {
                $dataEasyOrderPusher = new DataEasyOrderPusherService();
                $dataEasyOrderPusher->pushOrderToApi($order);
                Log::info('API Order pushed to DataEasy API', ['orderId' => $order->id, 'network' => $order->network]);
            } elseif (in_array(strtolower($order->network), ['telecel', 'ishare', 'bigtime']) && $codecraftEnabled) {
                $codeCraftOrderPusher = new CodeCraftOrderPusherService();
                $codeCraftOrderPusher->pushOrderToApi($order);
                Log::info('API Order pushed to CodeCraft API', ['orderId' => $order->id, 'network' => $order->network]);
            } else {
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
