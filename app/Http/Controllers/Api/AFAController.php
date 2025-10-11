<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AFAProduct;
use App\Models\AFAOrders;
use Illuminate\Support\Facades\DB;

class AFAController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $afaOrders = auth()->user()->afaOrders()->with('afaproduct')->latest()->get();
        return response()->json($afaOrders);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'afa_product_id' => 'required|exists:afa_products,id',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'dob' => 'required|date',
            'occupation' => 'required|string|max:255',
            'region' => 'required|string|max:255'
        ]);

        $afaProduct = AFAProduct::findOrFail($request->afa_product_id);

        if ($afaProduct->status !== 'IN_STOCK') {
            return response()->json(['error' => 'Product is out of stock'], 400);
        }

        if (auth()->user()->wallet_balance < $afaProduct->price) {
            return response()->json(['error' => 'Insufficient funds'], 400);
        }

        DB::transaction(function() use ($request, $afaProduct) {
            $afaOrder = AFAOrders::create([
                'user_id' => auth()->id(),
                'Afa_product_id' => $request->afa_product_id,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'dob' => $request->dob,
                'occupation' => $request->occupation,
                'region' => $request->region,
                'status' => 'PENDING'
            ]);

            auth()->user()->decrement('wallet_balance', $afaProduct->price);
        });

        return response()->json(['message' => 'AFA order created successfully'], 201);
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

    /**
     * Get available AFA products
     */
    public function getProducts()
    {
        $products = AFAProduct::where('status', 'IN_STOCK')->get();
        return response()->json($products);
    }
}
