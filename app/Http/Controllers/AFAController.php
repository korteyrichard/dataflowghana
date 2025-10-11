<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\AFAProduct;
use App\Models\AFAOrders;
use Illuminate\Support\Facades\DB;

class AFAController extends Controller
{
    public function index()
    {
        $afaProducts = AFAProduct::where('status', 'IN_STOCK')->get();
        $afaOrders = auth()->user()->afaOrders()->with('afaproduct')->latest()->get();
        
        return Inertia::render('Dashboard/AFARegistration', [
            'afaProducts' => $afaProducts,
            'afaOrders' => $afaOrders
        ]);
    }

    public function afaOrders()
    {
        $afaOrders = auth()->user()->afaOrders()->with('afaproduct')->latest()->get();
        return Inertia::render('Dashboard/AFAOrders', [
            'afaOrders' => $afaOrders
        ]);
    }

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
            return back()->withErrors(['error' => 'Product is out of stock']);
        }

        if (auth()->user()->wallet_balance < $afaProduct->price) {
            return back()->withErrors(['error' => 'Insufficient funds']);
        }

        DB::transaction(function() use ($request, $afaProduct) {
            AFAOrders::create([
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

        return redirect()->route('dashboard.afa')->with('success', 'AFA order created successfully!');
    }
}