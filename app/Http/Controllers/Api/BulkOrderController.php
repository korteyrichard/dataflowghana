<?php

// namespace App\Http\Controllers\Api;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use App\Models\Order;
// use App\Models\Product;
// use App\Models\ProductVariant;
// use Illuminate\Support\Facades\DB;

// class BulkOrderController extends Controller
// {
//     /**
//      * Display a listing of the resource.
//      */
//     public function index()
//     {
//         //
//     }

//     /**
//      * Store a newly created resource in storage.
//      */
//     public function store(Request $request)
//     {
//         $request->validate([
//             'orders' => 'required|string',
//             'network' => 'required|string'
//         ]);

//         $orderLines = array_filter(array_map('trim', explode('\n', $request->orders)));
//         $totalCost = 0;
//         $parsedOrders = [];

//         foreach ($orderLines as $line) {
//             $parts = preg_split('/\s+/', trim($line));
//             if (count($parts) < 2) continue;

//             $beneficiaryNumber = $parts[0];
//             $dataSize = $parts[1];

//             $variant = ProductVariant::whereHas('product', function($q) use ($request) {
//                 $q->where('network', $request->network)
//                   ->where('product_type', 'customer_product');
//             })->whereJsonContains('variant_attributes->size', $dataSize)
//               ->where('status', 'IN STOCK')
//               ->first();

//             if (!$variant) {
//                 return response()->json(['error' => "Data bundle {$dataSize} not found for {$request->network}"], 400);
//             }

//             $parsedOrders[] = [
//                 'beneficiary_number' => $beneficiaryNumber,
//                 'variant' => $variant,
//                 'price' => $variant->price
//             ];
//             $totalCost += $variant->price;
//         }

//         if (auth()->user()->wallet_balance < $totalCost) {
//             return response()->json(['error' => 'Insufficient funds'], 400);
//         }

//         DB::transaction(function() use ($parsedOrders, $request, $totalCost) {
//             foreach ($parsedOrders as $orderData) {
//                 $order = Order::create([
//                     'user_id' => auth()->id(),
//                     'total' => $orderData['price'],
//                     'beneficiary_number' => $orderData['beneficiary_number'],
//                     'network' => $request->network
//                 ]);

//                 $order->products()->attach($orderData['variant']->product_id, [
//                     'quantity' => 1,
//                     'price' => $orderData['price'],
//                     'beneficiary_number' => $orderData['beneficiary_number'],
//                     'product_variant_id' => $orderData['variant']->id
//                 ]);
//             }

//             auth()->user()->decrement('wallet_balance', $totalCost);
//         });

//         return response()->json(['message' => 'Bulk orders created successfully', 'total_cost' => $totalCost], 201);
//     }

//     /**
//      * Display the specified resource.
//      */
//     public function show(string $id)
//     {
//         //
//     }

//     /**
//      * Update the specified resource in storage.
//      */
//     public function update(Request $request, string $id)
//     {
//         //
//     }

//     /**
//      * Remove the specified resource from storage.
//      */
//     public function destroy(string $id)
//     {
//         //
//     }
// }
