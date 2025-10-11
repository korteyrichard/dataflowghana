<?php

// namespace App\Http\Controllers\Api;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use App\Models\Order;
// use App\Models\ProductVariant;
// use Illuminate\Support\Facades\DB;


// class ExcelOrderController extends Controller
// {
//     /**
//      * Store orders from Excel file upload
//      */
//     public function store(Request $request)
//     {
//         $request->validate([
//             'csv_file' => 'required|file|mimes:csv,txt',
//             'network' => 'required|string',
//             'data_size' => 'required|string'
//         ]);

//         $file = $request->file('csv_file');
//         $csvData = array_map('str_getcsv', file($file->getPathname()));
        
//         $totalCost = 0;
//         $parsedOrders = [];

//         $variant = ProductVariant::whereHas('product', function($q) use ($request) {
//             $q->where('network', $request->network)
//               ->where('product_type', 'customer_product');
//         })->whereJsonContains('variant_attributes->size', $request->data_size)
//           ->where('status', 'IN STOCK')
//           ->first();

//         if (!$variant) {
//             return response()->json(['error' => "Data bundle {$request->data_size} not found for {$request->network}"], 400);
//         }

//         foreach (array_slice($csvData, 1) as $row) {
//             if (empty($row[0]) || empty($row[1])) continue;

//             $beneficiaryNumber = trim($row[0]);
//             $quantity = (int) $row[1];

//             for ($i = 0; $i < $quantity; $i++) {
//                 $parsedOrders[] = [
//                     'beneficiary_number' => $beneficiaryNumber,
//                     'variant' => $variant,
//                     'price' => $variant->price
//                 ];
//                 $totalCost += $variant->price;
//             }
//         }

//         if (auth()->user()->wallet_balance < $totalCost) {
//             return response()->json(['error' => 'Insufficient funds'], 400);
//         }

//         DB::transaction(function() use ($parsedOrders, $request, $totalCost, $variant) {
//             foreach ($parsedOrders as $orderData) {
//                 $order = Order::create([
//                     'user_id' => auth()->id(),
//                     'total' => $orderData['price'],
//                     'beneficiary_number' => $orderData['beneficiary_number'],
//                     'network' => $request->network
//                 ]);

//                 $order->products()->attach($variant->product_id, [
//                     'quantity' => 1,
//                     'price' => $orderData['price'],
//                     'beneficiary_number' => $orderData['beneficiary_number'],
//                     'product_variant_id' => $variant->id
//                 ]);
//             }

//             auth()->user()->decrement('wallet_balance', $totalCost);
//         });

//         return response()->json([
//             'message' => 'CSV orders created successfully', 
//             'total_orders' => count($parsedOrders),
//             'total_cost' => $totalCost
//         ], 201);
//     }
// }