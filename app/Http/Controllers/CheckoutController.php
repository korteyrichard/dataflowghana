<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Cart;

class CheckoutController extends Controller
{
    public function index()
    {
        $cartItems = Cart::where('user_id', auth()->id())
            ->with(['product', 'productVariant'])
            ->get()
            ->map(function($item) {
                $size = 'Unknown';
                if ($item->productVariant && isset($item->productVariant->variant_attributes['size'])) {
                    $size = strtoupper($item->productVariant->variant_attributes['size']);
                }
                
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $size,
                    'beneficiary_number' => $item->beneficiary_number,
                    'product' => [
                        'name' => $item->product ? $item->product->name : 'Data Bundle',
                        'price' => $item->price ?? ($item->productVariant ? $item->productVariant->price : 0),
                        'network' => $item->network ?? ($item->product ? $item->product->network : 'Unknown'),
                        'expiry' => $item->product ? $item->product->expiry : '30 Days'
                    ]
                ];
            });
        $walletBalance = auth()->user()->wallet_balance;

        return Inertia::render('Dashboard/checkout', [
            'cartItems' => $cartItems,
            'walletBalance' => $walletBalance,
        ]);
    }
}
