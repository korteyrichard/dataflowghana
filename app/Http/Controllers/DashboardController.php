<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Transaction;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Services\MoolreSmsService;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Get products for displaying expiry information
        $products = Product::all();
        
        $cartCount = 0;
        $cartItems = [];
        $walletBalance = 0;
        $orders = [];
        
        if (auth()->check()) {
            $cartCount = Cart::where('user_id', auth()->id())->count();
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
            $walletBalance = $user->wallet_balance;
            $orders = Order::where('user_id', $user->id)->with('products')->get();
        }
        
        // Calculate dashboard stats
        $totalSales = Transaction::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('type', 'order')
            ->sum('amount');
            
        $todaySales = Transaction::where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('type', 'order')
            ->whereDate('created_at', today())
            ->sum('amount');
            
        $pendingOrdersCount = Order::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'PENDING'])
            ->count();
            
        $processingOrdersCount = Order::where('user_id', $user->id)
            ->whereIn('status', ['processing', 'PROCESSING'])
            ->count();
        
        return Inertia::render('Dashboard/dashboard', [
            'cartCount' => $cartCount,
            'cartItems' => $cartItems,
            'walletBalance' => $walletBalance,
            'orders' => $orders,
            'totalSales' => $totalSales ?? 0,
            'todaySales' => $todaySales ?? 0,
            'pendingOrders' => $pendingOrdersCount ?? 0,
            'processingOrders' => $processingOrdersCount ?? 0,
            'products' => $products,
        ]);
    }



    public function viewCart()
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
        return Inertia::render('Dashboard/Cart', ['cartItems' => $cartItems]);
    }

    public function removeFromCart($id)
    {
        Cart::where('user_id', auth()->id())->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Removed from cart']);
    }

    public function transactions()
    {
        $transactions = Transaction::where('user_id', auth()->id())->latest()->get();
        return Inertia::render('Dashboard/transactions', [
            'transactions' => $transactions,
        ]);
    }

    /**
     * Add to the authenticated user's wallet balance via Paystack
     */
    public function addToWallet(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user = auth()->user();
        $reference = 'wallet_' . Str::random(16);
        
        // Store pending transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'order_id' => null,
            'amount' => $request->amount,
            'status' => 'pending',
            'type' => 'topup',
            'description' => 'Wallet top-up of GHS ' . number_format($request->amount, 2),
            'reference' => $reference,
        ]);
        
        // Initialize Paystack payment
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secret_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => $request->amount * 100, // Convert to kobo
            'callback_url' => route('wallet.callback'),
            'reference' => $reference,
            'metadata' => [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'type' => 'wallet_topup',
                'actual_amount' => $request->amount
            ]
        ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'payment_url' => $response->json('data.authorization_url')
            ]);
        }

        // Capture Paystack reference even on failure for tracking purposes
        $responseData = $response->json();
        $paystackReference = $responseData['data']['reference'] ?? null;
        
        $transaction->update([
            'status' => 'failed',
            'reference' => $paystackReference ?: $reference // Use Paystack reference if available, otherwise keep original
        ]);
        
        return response()->json(['success' => false, 'message' => 'Payment initialization failed']);
    }

    public function handleWalletCallback(Request $request)
    {
        $reference = $request->reference;
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secret_key'),
        ])->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful() && $response->json('data.status') === 'success') {
            $paymentData = $response->json('data');
            $metadata = $paymentData['metadata'];
            
            $transaction = Transaction::find($metadata['transaction_id']);
            $user = auth()->user();
            
            if ($transaction && $transaction->status === 'pending') {
                // Get the amount from metadata or transaction record
                $amount = isset($metadata['actual_amount']) ? $metadata['actual_amount'] : $transaction->amount;
                
                // Update wallet balance with the full amount
                $user->wallet_balance += $amount;
                $user->save();
                
                // Update transaction status
                $transaction->update(['status' => 'completed']);
                
                // Send SMS notification
                if ($user->phone) {
                    $smsService = new MoolreSmsService();
                    $message = "Your wallet has been topped up with GHS " . number_format($amount, 2) . ". New balance: GHS " . number_format($user->wallet_balance, 2);
                    $smsService->sendSms($user->phone, $message);
                }
            }
        }

        return redirect()->route('dashboard')->with('success', 'Wallet topped up successfully!');
    }

    public function getBundleSizes(Request $request)
    {
        $network = $request->get('network');
        
        if (!$network) {
            return response()->json(['success' => false, 'message' => 'Network is required']);
        }
        
        $user = auth()->user();
        
        // Determine product type based on user role
        if ($user->role === 'customer') {
            $productType = 'customer_product';
        } elseif ($user->role === 'agent') {
            $productType = 'agent_product';
        } elseif ($user->role === 'dealer' || $user->role === 'admin') {
            $productType = 'dealer_product';
        } else {
            $productType = 'customer_product';
        }
        
        $product = Product::where('network', $network)
            ->where('product_type', $productType)
            ->first();
        
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found']);
        }
        
        $variants = $product->variants()
            ->where('status', 'IN STOCK')
            ->get()
            ->map(function($variant) {
                $size = $variant->variant_attributes['size'] ?? null;
                
                // Skip variants without proper size attribute
                if (!$size) {
                    return null;
                }
                $displaySize = strtoupper(str_replace('gb', ' GB', $size));
                if ($size === '0.5gb') {
                    $displaySize = '500 MB';
                }
                return [
                    'value' => preg_replace('/[^0-9.]/', '', $size),
                    'label' => $displaySize,
                    'price' => $variant->price
                ];
            })
            ->filter(function($item) {
                return $item !== null && $item['value'] !== '' && $item['value'] !== 'unknown';
            })
            ->sortBy(function($item) {
                return (float) $item['value'];
            })
            ->values();
            
        return response()->json(['success' => true, 'sizes' => $variants]);
    }
}
