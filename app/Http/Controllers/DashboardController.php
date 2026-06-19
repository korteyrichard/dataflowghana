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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\MoolreSmsService;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $userId = $user->id;
        $today = today();
        
        // Batch all dashboard queries efficiently
        $cartCount = 0;
        $cartItems = [];
        $walletBalance = $user->wallet_balance;
        $orders = [];
        
        if (auth()->check()) {
            // Single query with count + get in one round trip
            $cartQuery = Cart::where('user_id', $userId)
                ->with(['product', 'productVariant'])
                ->select('carts.*');
            $cartCount = $cartQuery->count();
            $cartItems = $cartQuery->get()
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
            
            // Limit orders to top 10 to prevent large result sets
            $orders = Order::where('user_id', $userId)
                ->select('id', 'status', 'total', 'created_at')
                ->latest()
                ->limit(10)
                ->get();
        }
        
        // Get products with variant pricing
        $products = Product::where('status', 'IN STOCK')
            ->select('id', 'name', 'network', 'expiry', 'product_type')
            ->with('variants:product_id,price,variant_attributes')
            ->limit(50)
            ->get()
            ->map(function($product) {
                return array_merge($product->toArray(), [
                    'price' => $product->getPriceRange()
                ]);
            });
        
        // Calculate total sales from completed transactions (more accurate)
        $totalSales = Transaction::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereIn('type', ['order', 'bulk_order'])
            ->sum('amount');
        
        // Calculate today's sales from completed transactions
        $todaySales = Transaction::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereIn('type', ['order', 'bulk_order'])
            ->whereDate('created_at', $today)
            ->sum('amount');
        
        // Get all stats in single query using CASE
        $orderStats = DB::selectOne(
            'SELECT '
            . 'SUM(CASE WHEN status IN ("pending", "PENDING") THEN 1 ELSE 0 END) as pending_count, '
            . 'SUM(CASE WHEN status IN ("processing", "PROCESSING") THEN 1 ELSE 0 END) as processing_count '
            . 'FROM orders WHERE user_id = ?',
            [$userId]
        );
        
        return Inertia::render('Dashboard/dashboard', [
            'cartCount' => $cartCount,
            'cartItems' => $cartItems,
            'walletBalance' => $walletBalance,
            'orders' => $orders,
            'totalSales' => (float) $totalSales ?? 0,
            'todaySales' => (float) $todaySales ?? 0,
            'pendingOrders' => $orderStats->pending_count ?? 0,
            'processingOrders' => $orderStats->processing_count ?? 0,
            'products' => $products,
        ]);
    }



    public function viewCart()
    {
        $cartItems = Cart::where('user_id', auth()->id())
            ->with(['product:id,name,network,expiry', 'productVariant:id,price,variant_attributes'])
            ->select('id', 'product_id', 'price', 'beneficiary_number', 'network')
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
        $transactions = Transaction::where('user_id', auth()->id())
            ->select('id', 'amount', 'status', 'type', 'description', 'created_at')
            ->latest()
            ->paginate(20);
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
            'amount' => 'required|numeric|min:1|max:50000',
        ]);

        $user = auth()->user();
        $reference = 'wallet_' . Str::random(16);
        
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'order_id' => null,
            'amount' => $request->amount,
            'balance_before' => $user->wallet_balance,
            'balance_after' => $user->wallet_balance,
            'status' => 'pending',
            'type' => 'topup',
            'description' => 'Wallet top-up of GHS ' . number_format($request->amount, 2),
            'reference' => $reference,
        ]);
        
        $response = Http::timeout(15)
            ->retry(2, 100)
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('paystack.secret_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $request->amount * 100,
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

        $transaction->update(['status' => 'failed']);
        return response()->json(['success' => false, 'message' => 'Payment initialization failed'], 400);
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
            
            // Use database transaction with locking
            DB::transaction(function () use ($metadata) {
                $transaction = Transaction::lockForUpdate()->find($metadata['transaction_id']);
                
                if (!$transaction || $transaction->status === 'completed') {
                    return;
                }
                
                $user = User::lockForUpdate()->find($metadata['user_id']);
                $amount = isset($metadata['actual_amount']) ? $metadata['actual_amount'] : $transaction->amount;
                $balanceBefore = $user->wallet_balance;
                
                $user->increment('wallet_balance', $amount);
                $balanceAfter = $balanceBefore + $amount;
                
                $transaction->update([
                    'status' => 'completed',
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter
                ]);
                
                if ($user->phone) {
                    $smsService = new MoolreSmsService();
                    $message = "Your wallet has been topped up with GHS " . number_format($amount, 2) . ". New balance: GHS " . number_format($balanceAfter, 2);
                    $smsService->sendSms($user->phone, $message);
                }
            });
        }

        return redirect()->route('dashboard')->with('success', 'Wallet topped up successfully!');
    }

    public function getBundleSizes(Request $request)
    {
        $network = $request->get('network');
        
        if (!$network) {
            return response()->json(['success' => false, 'message' => 'Network is required']);
        }
        
        $allowedNetworks = ['MTN', 'MTN EXPRESS', 'TELECEL', 'ISHARE', 'BIGTIME'];
        if (!in_array($network, $allowedNetworks, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid network']);
        }
        
        $user = auth()->user();
        
        // Determine product type based on user role
        if ($user->role === 'customer') {
            $productType = 'customer_product';
        } elseif ($user->role === 'agent') {
            $productType = 'agent_product';
        } elseif ($user->role === 'superAgent') {
            $productType = 'super_agent_product';
        } elseif ($user->role === 'elite') {
            $productType = 'elite_product';
        } elseif ($user->role === 'dealer' || $user->role === 'admin') {
            $productType = 'dealer_product';
        } else {
            $productType = 'customer_product';
        }
        
        $product = Product::where('network', $network)
            ->where('product_type', $productType)
            ->where('status', 'IN STOCK')
            ->first();
        
        // If no product found and network is MTN EXPRESS, try MTN network
        if (!$product && $network === 'MTN EXPRESS') {
            $product = Product::where('network', 'MTN')
                ->whereRaw("LOWER(name) LIKE ?", ['%mtn express%'])
                ->where('product_type', $productType)
                ->where('status', 'IN STOCK')
                ->first();
        }
        
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
