<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\MoolreSmsService;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index()
    {
        $usersCount = User::count();
        $productsCount = Product::count();
        $ordersCount = Order::count();

        $today = now()->today();
        $todayUsersCount = User::whereDate('created_at', $today)->count();
        $todayOrdersCount = Order::whereDate('created_at', $today)->count();

        // Get past 30 days sales data using raw query for performance
        $past30Days = DB::select(
            'SELECT DATE_FORMAT(DATE_SUB(DATE(?), INTERVAL 29 - a.x DAY), "%Y-%m-%d") as date, '
            . 'DATE_FORMAT(DATE_SUB(DATE(?), INTERVAL 29 - a.x DAY), "%b %d") as formatted_date, '
            . 'COALESCE(SUM(o.total), 0) as sales '
            . 'FROM (SELECT 0 as x UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 '
            . 'UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 '
            . 'UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 '
            . 'UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 '
            . 'UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29) a '
            . 'LEFT JOIN orders o ON DATE(o.created_at) = DATE_SUB(DATE(?), INTERVAL 29 - a.x DAY) '
            . 'GROUP BY a.x ORDER BY a.x ASC',
            [now(), now(), now()]
        );
        
        $past30DaysFormatted = array_map(function($row) {
            return [
                'date' => $row->formatted_date,
                'fullDate' => $row->date,
                'sales' => (float) $row->sales,
            ];
        }, $past30Days);

        return Inertia::render('Admin/Dashboard', [
            'usersCount' => $usersCount,
            'productsCount' => $productsCount,
            'ordersCount' => $ordersCount,
            'todayUsersCount' => $todayUsersCount,
            'todayOrdersCount' => $todayOrdersCount,
            'past30DaysSales' => $past30DaysFormatted,
            'jaybartOrderPusherEnabled' => (bool) Setting::get('jaybart_order_pusher_enabled', 1),
            'codecraftOrderPusherEnabled' => (bool) Setting::get('codecraft_order_pusher_enabled', 1),
            'datamasterOrderPusherEnabled' => (bool) Setting::get('datamaster_order_pusher_enabled', 1),
            'dataeasyOrderPusherEnabled' => (bool) Setting::get('dataeasy_order_pusher_enabled', 0),
            'dataSourceOrderPusherEnabled' => (bool) Setting::get('datasource_order_pusher_enabled', 1),
            'codecraftMtnOrderPusherEnabled' => (bool) Setting::get('codecraft_mtn_order_pusher_enabled', 0),
        ]);
    }

    /**
     * Display the admin users page.
     */
    public function users(Request $request)
    {
        $query = User::query();

        if ($request->filled('username')) {
            $query->where('name', 'like', '%' . $request->input('username') . '%');
        }

        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->input('phone') . '%');
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // Get stats in one query
        $stats = DB::selectOne(
            'SELECT COUNT(*) as total, '
            . 'SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as customers, '
            . 'SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as agents, '
            . 'SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as admins, '
            . 'COALESCE(SUM(wallet_balance), 0) as total_wallet '
            . 'FROM users',
            ['customer', 'agent', 'admin']
        );

        // Calculate today's wallet topup (sum of admin_credit, topup, and wallet_topup)
        $todaysTopup = Transaction::whereDate('created_at', today())
            ->where('status', 'completed')
            ->whereIn('type', ['admin_credit', 'topup', 'wallet_topup'])
            ->sum('amount');

        // Daily admin credit
        $todaysAdminCredit = Transaction::whereDate('created_at', today())
            ->where('status', 'completed')
            ->where('type', 'admin_credit')
            ->sum('amount');

        // Daily topup (topup + wallet_topup only, excluding admin_credit)
        $todaysTopupOnly = Transaction::whereDate('created_at', today())
            ->where('status', 'completed')
            ->whereIn('type', ['topup', 'wallet_topup'])
            ->sum('amount');

        return Inertia::render('Admin/Users', [
            'users' => $query->select('id', 'name', 'email', 'phone', 'role', 'wallet_balance', 'created_at', 'updated_at')->paginate(15)->appends($request->query()),
            'filterUsername' => $request->input('username', ''),
            'filterEmail' => $request->input('email', ''),
            'filterPhone' => $request->input('phone', ''),
            'filterRole' => $request->input('role', ''),
            'userStats' => [
                'total' => $stats->total,
                'customers' => $stats->customers,
                'agents' => $stats->agents,
                'admins' => $stats->admins,
                'totalWalletBalance' => $stats->total_wallet,
                'todaysTopup' => $todaysTopup,
                'todaysAdminCredit' => $todaysAdminCredit,
                'todaysTopupOnly' => $todaysTopupOnly,
            ],
        ]);
    }

    /**
     * Display the admin products page.
     */
    public function products(Request $request)
    {
        $products = Product::with('variants');

        if ($request->has('network') && $request->input('network') !== '') {
            $products->where('network', 'like', '%' . $request->input('network') . '%');
        }

        $productsData = $products->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'network' => $product->network,
                'product_type' => $product->product_type,
                'expiry' => $product->expiry,
                'has_variants' => $product->has_variants,
                'status' => $product->status,
                'variants' => $product->variants,
                'price_range' => $product->getPriceRange(),
            ];
        });

        return Inertia::render('Admin/Products', [
            'products' => $productsData,
            'filterNetwork' => $request->input('network', ''),
        ]);
    }

    /**
     * Display the admin orders page.
     */
    public function orders(Request $request)
    {
        $query = Order::with([
            'products' => function($q) { $q->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id'); },
            'user:id,name,email'
        ])->select('id', 'user_id', 'network', 'status', 'api_status', 'total', 'created_at')->latest()->orderByDesc('id');

        if ($request->filled('network')) {
            $query->where('network', 'like', '%' . $request->input('network') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('order_id')) {
            $query->where('id', 'like', '%' . $request->input('order_id') . '%');
        }

        if ($request->filled('beneficiary_number')) {
            $query->whereHas('products', function($q) use ($request) {
                $q->where('order_product.beneficiary_number', 'like', '%' . $request->input('beneficiary_number') . '%');
            });
        }

        if ($request->filled('username')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('username') . '%');
            });
        }

        if ($request->filled('email')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('email', 'like', '%' . $request->input('email') . '%');
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $paginatedOrders = $query->paginate(100)->appends($request->query());
        
        $paginatedOrders->getCollection()->transform(function($order) {
            $order->products = $order->products->map(function($product) {
                if ($product->pivot->product_variant_id) {
                    $variant = \App\Models\ProductVariant::select('variant_attributes')->find($product->pivot->product_variant_id);
                    if ($variant && isset($variant->variant_attributes['size'])) {
                        $product->size = strtoupper($variant->variant_attributes['size']);
                    }
                }
                return $product;
            });
            return $order;
        });

        $dailyTotalSales = Order::whereDate('created_at', today())->sum('total');
        $allNetworks = Order::select('network')->distinct()->where('network', '!=', null)->orderBy('network')->pluck('network');
        $pendingOrdersCount = Order::where('status', 'pending')->count();
        $processingOrdersCount = Order::where('status', 'processing')->count();

        return Inertia::render('Admin/Orders', [
            'orders' => $paginatedOrders,
            'allNetworks' => $allNetworks,
            'filterNetwork' => $request->input('network', ''),
            'filterStatus' => $request->input('status', ''),
            'searchOrderId' => $request->input('order_id', ''),
            'searchBeneficiaryNumber' => $request->input('beneficiary_number', ''),
            'searchUsername' => $request->input('username', ''),
            'searchEmail' => $request->input('email', ''),
            'filterDate' => $request->input('date', ''),
            'dailyTotalSales' => $dailyTotalSales,
            'pendingOrdersCount' => $pendingOrdersCount,
            'processingOrdersCount' => $processingOrdersCount,
        ]);
    }

    /**
     * Delete an order.
     */
    public function deleteOrder(Order $order)
    {
        $order->delete();
        return redirect()->back()->with('success', 'Order deleted successfully.');
    }

    /**
     * Update an order's status.
     */
    public function updateOrderStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|string|in:pending,processing,completed,cancelled',
        ]);

        $oldStatus = $order->status;
        $order->update(['status' => $request->status]);

        // Handle automatic refund when order is cancelled
        if ($request->status === 'cancelled' && $oldStatus !== 'cancelled') {
            $user = $order->user;
            $refundAmount = $order->total;
            $balanceBefore = $user->wallet_balance;
            
            // Add refund to user's wallet
            $user->increment('wallet_balance', $refundAmount);
            $balanceAfter = $user->fresh()->wallet_balance;
            
            // Create refund transaction record
            Transaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'amount' => $refundAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => 'completed',
                'type' => 'refund',
                'description' => "Refund for cancelled order #{$order->id}",
            ]);
            
            // Send SMS notification for refund
            if ($user->phone) {
                $smsService = new MoolreSmsService();
                $message = "Your order #{$order->id} has been cancelled and GHS " . number_format($refundAmount, 2) . " has been refunded to your wallet.";
                $smsService->sendSms($user->phone, $message);
            }
        }

        // Send SMS if status changed to completed
        if ($request->status === 'completed' && $oldStatus !== 'completed' && $order->user->phone) {
            $smsService = new MoolreSmsService();
            $message = "Your order #{$order->id} for {$order->products->first()->name} to {$order->beneficiary_number} has been completed. Total: GHS " . number_format($order->total, 2);
            $smsService->sendSms($order->user->phone, $message);
        }

        return redirect()->back()->with('success', 'Order status updated successfully.');
    }

    /**
     * Bulk update order statuses.
     */
    public function bulkUpdateOrderStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'status' => 'required|string|in:pending,processing,completed,cancelled',
        ]);

        // Handle refunds when cancelling
        if ($request->status === 'cancelled') {
            $orders = Order::with('user')->whereIn('id', $request->order_ids)->where('status', '!=', 'cancelled')->get();
            
            $transactionData = [];
            $refundsByUser = [];

            foreach ($orders as $order) {
                $userId = $order->user_id;
                $refundsByUser[$userId] = ($refundsByUser[$userId] ?? 0) + $order->total;
            }

            // Batch increment wallets
            foreach ($refundsByUser as $userId => $totalRefund) {
                User::where('id', $userId)->increment('wallet_balance', $totalRefund);
            }

            // Build transaction records
            $userBalances = User::whereIn('id', array_keys($refundsByUser))->pluck('wallet_balance', 'id');
            foreach ($orders as $order) {
                $balanceAfter = $userBalances[$order->user_id];
                $transactionData[] = [
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'amount' => $order->total,
                    'balance_before' => $balanceAfter - $refundsByUser[$order->user_id],
                    'balance_after' => $balanceAfter,
                    'status' => 'completed',
                    'type' => 'refund',
                    'description' => "Refund for cancelled order #{$order->id}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($transactionData)) {
                Transaction::insert($transactionData);
            }
        }

        $updatedCount = Order::whereIn('id', $request->order_ids)
            ->update(['status' => $request->status]);

        return redirect()->back()->with('success', "Updated {$updatedCount} order(s) successfully.");
    }

    /**
     * Display the admin transactions page.
     */
    public function transactions(Request $request)
    {
        $query = Transaction::with('user:id,name', 'admin:id,name', 'order:id')
            ->where('status', 'completed')
            ->select('id', 'user_id', 'admin_id', 'order_id', 'amount', 'balance_before', 'balance_after', 'status', 'type', 'description', 'created_at')
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return Inertia::render('Admin/Transactions', [
            'transactions' => $query->paginate(15),
            'filterType' => $request->input('type', ''),
        ]);
    }

    /**
     * Store a new user.
     */
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:customer,agent,admin,dealer,elite,superAgent',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('admin.users');
    }

    /**
     * Update the user's role.
     */
    public function updateUserRole(Request $request, User $user)
    {
        \Log::info('Update role request', ['role' => $request->role, 'user_id' => $user->id]);
        
        $request->validate([
            'role' => 'required|string|in:customer,agent,admin,dealer,elite,superAgent',
        ]);

        $user->role = $request->role;
        \Log::info('User role set', ['role' => $user->role, 'type' => gettype($user->role)]);
        $user->save();

        return redirect()->back()->with('success', 'User role updated successfully.');
    }

    /**
     * Delete the user.
     */
    public function deleteUser(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users');
    }

    /**
     * Credit user's wallet.
     */
    public function creditWallet(Request $request, User $user, MoolreSmsService $smsService)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $amount = $request->amount;
        $balanceBefore = $user->wallet_balance;
        $user->increment('wallet_balance', $amount);
        $balanceAfter = $user->fresh()->wallet_balance;

        // Create transaction record
        Transaction::create([
            'user_id' => $user->id,
            'admin_id' => auth()->id(),
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'completed',
            'type' => 'admin_credit',
            'description' => "Admin credit to wallet by " . auth()->user()->name,
            'reference' => 'ADMIN_CREDIT_' . time() . '_' . $user->id,
        ]);

        // Send SMS notification
        $message = "Your wallet has been credited with GHS " . number_format($amount, 2) . ". New balance: GHS " . number_format($user->wallet_balance, 2);
        $smsService->sendSms($user->phone, $message);

        return redirect()->route('admin.users')->with('success', 'Wallet credited successfully.');
    }

    /**
     * Debit user's wallet.
     */
    public function debitWallet(Request $request, User $user, MoolreSmsService $smsService)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($user->wallet_balance < $request->amount) {
            return redirect()->route('admin.users')->with('error', 'Insufficient wallet balance.');
        }

        $amount = $request->amount;
        $balanceBefore = $user->wallet_balance;
        $user->decrement('wallet_balance', $amount);
        $balanceAfter = $user->fresh()->wallet_balance;

        // Create transaction record
        Transaction::create([
            'user_id' => $user->id,
            'admin_id' => auth()->id(),
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'completed',
            'type' => 'admin_debit',
            'description' => "Admin debit from wallet by " . auth()->user()->name,
            'reference' => 'ADMIN_DEBIT_' . time() . '_' . $user->id,
        ]);

        // Send SMS notification
        $message = "Your wallet has been debited with GHS " . number_format($amount, 2) . ". New balance: GHS " . number_format($user->wallet_balance, 2);
        $smsService->sendSms($user->phone, $message);

        return redirect()->route('admin.users')->with('success', 'Wallet debited successfully.');
    }

    /**
     * Store a new product.
     */
    public function storeProduct(Request $request)
    {
        \Log::info('=== STORE PRODUCT REQUEST START ===');
        \Log::info('Request Method:', [$request->method()]);
        \Log::info('Request URL:', [$request->url()]);
        \Log::info('Request Headers:', $request->headers->all());
        \Log::info('Store Product Request Data:', $request->all());
        \Log::info('Request Input Count:', [count($request->all())]);
        \Log::info('complete request',[$request->all()]);
        
        try {
            \Log::info('Starting validation...');
            $request->validate([
                'name' => 'required|string|max:255',
                'network' => 'required|in:MTN,Telecel,Ishare,Bigtime',
                'description' => 'required|string|max:255',
                'expiry' => 'required|in:non expiry,30 days,24 hours',
                'product_type' => 'required|in:agent_product,customer_product,dealer_product,elite_product,super_agent_product',
                'status' => 'required|in:IN STOCK,OUT OF STOCK',
                'variants' => 'required|array|min:1',
                'variants.*.price' => 'required|numeric|min:0',
                'variants.*.quantity' => 'required|string',
                'variants.*.status' => 'required|in:IN STOCK,OUT OF STOCK',
            ]);
            \Log::info('Validation passed successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed:', $e->errors());
            throw $e;
        }

        try {
            \Log::info('Creating product with data:', [
                'name' => $request->name,
                'network' => $request->network,
                'description' => $request->description,
                'expiry' => $request->expiry,
                'product_type' => $request->product_type,
                'has_variants' => count($request->variants) > 1,
            ]);
            
            $product = Product::create([
                'name' => $request->name,
                'network' => $request->network,
                'description' => $request->description,
                'expiry' => $request->expiry,
                'product_type' => $request->product_type,
                'status' => $request->status,
                'has_variants' => count($request->variants) > 1,
            ]);

            \Log::info('Product created with ID: ' . $product->id);

            foreach ($request->variants as $index => $variantData) {
                \Log::info('Creating variant ' . ($index + 1) . ':', $variantData);
                
                ProductVariant::create([
                    'product_id' => $product->id,
                    'price' => $variantData['price'],
                    'quantity' => $variantData['quantity'],
                    'status' => $variantData['status'],
                    'variant_attributes' => ['size' => $variantData['quantity']],
                ]);
            }

            \Log::info('Product and variants created successfully');
            return redirect()->route('admin.products')->with('success', 'Product created successfully.');
        } catch (\Exception $e) {
            \Log::error('=== PRODUCT CREATION FAILED ===');
            \Log::error('Error message: ' . $e->getMessage());
            \Log::error('Error file: ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Log::error('Request data at time of error:', $request->all());
            return redirect()->back()->withErrors(['error' => 'Failed to create product: ' . $e->getMessage()])->withInput();
        }
        
        \Log::info('=== STORE PRODUCT REQUEST END ===');
    }

    /**
     * Update a product.
     */
    public function updateProduct(Request $request, Product $product)
    {
        \Log::info('Update Product Request Data:', $request->all());
        \Log::info('Updating product ID: ' . $product->id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'network' => 'required|in:MTN,Telecel,Ishare,Bigtime',
            'description' => 'required|string|max:255',
            'expiry' => 'required|in:non expiry,30 days,24 hours',
            'product_type' => 'required|in:agent_product,customer_product,dealer_product,elite_product,super_agent_product',
            'status' => 'required|in:IN STOCK,OUT OF STOCK',
            'variants' => 'required|array|min:1',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.quantity' => 'required|string',
            'variants.*.status' => 'required|in:IN STOCK,OUT OF STOCK',
        ]);

        try {
            \DB::transaction(function () use ($request, $product) {
                $product->update([
                    'name' => $request->name,
                    'network' => $request->network,
                    'description' => $request->description,
                    'expiry' => $request->expiry,
                    'product_type' => $request->product_type,
                    'status' => $request->status,
                    'has_variants' => count($request->variants) > 1,
                ]);

                $existingVariants = $product->variants;
                $requestVariants = collect($request->variants);

                // Update existing variants or create new ones
                $requestVariants->each(function ($variantData, $index) use ($product, $existingVariants) {
                    if (isset($existingVariants[$index])) {
                        // Update existing variant
                        $existingVariants[$index]->update([
                            'price' => $variantData['price'],
                            'quantity' => $variantData['quantity'],
                            'status' => $variantData['status'],
                            'variant_attributes' => ['size' => $variantData['quantity']],
                        ]);
                    } else {
                        // Create new variant
                        $product->variants()->create([
                            'price' => $variantData['price'],
                            'quantity' => $variantData['quantity'],
                            'status' => $variantData['status'],
                            'variant_attributes' => ['size' => $variantData['quantity']],
                        ]);
                    }
                });

                // Delete excess variants if any
                if ($existingVariants->count() > $requestVariants->count()) {
                    $variantsToDelete = $existingVariants->slice($requestVariants->count());
                    foreach ($variantsToDelete as $variant) {
                        $variant->delete();
                    }
                }
            });

            return redirect()->route('admin.products')->with('success', 'Product updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update product: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update product: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Delete a product.
     */
    public function deleteProduct(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products');
    }

    /**
     * Display user transaction history.
     */
    public function userTransactions(Request $request, User $user)
    {
        $query = Transaction::where('user_id', $user->id)
            ->where('status', 'completed')
            ->with('order', 'admin')
            ->select('id', 'user_id', 'admin_id', 'order_id', 'amount', 'balance_before', 'balance_after', 'status', 'type', 'description', 'created_at')
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $transactions = $query->paginate(50)->appends($request->query());

        // Calculate totals for all transactions (not just current page)
        $allTransactions = Transaction::where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();
        
        $totalTopupAmount = $allTransactions->where('type', 'wallet_topup')->sum('amount') +
                          $allTransactions->where('type', 'topup')->sum('amount') +
                          $allTransactions->where('type', 'admin_credit')->sum('amount');
        
        $totalOrderAmount = $allTransactions->where('type', 'order')->sum('amount');
        $totalRefundAmount = $allTransactions->where('type', 'refund')->sum('amount');

        // Today's orders (sum of both 'order' and 'bulk_order' types)
        $todaysOrderAmount = Transaction::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereIn('type', ['order', 'bulk_order'])
            ->whereDate('created_at', today())
            ->sum('amount');

        // Last 7 days order sales for this user
        $last7DaysSales = Transaction::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereIn('type', ['order', 'bulk_order'])
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(amount) as sales')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('sales', 'date');

        $past7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $past7Days[] = [
                'date' => now()->subDays($i)->format('M d'),
                'fullDate' => $date,
                'sales' => (float) ($last7DaysSales[$date] ?? 0),
            ];
        }

        return Inertia::render('Admin/UserTransactions', [
            'user' => $user,
            'transactions' => $transactions,
            'totalTopupAmount' => $totalTopupAmount,
            'totalOrderAmount' => $totalOrderAmount,
            'totalRefundAmount' => $totalRefundAmount,
            'todaysOrderAmount' => $todaysOrderAmount,
            'past7DaysSales' => $past7Days,
            'filterType' => $request->input('type', ''),
        ]);
    }

    /**
     * Export selected orders to CSV.
     */
    public function exportOrders(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
        ]);

        $orders = Order::with(['products' => function($query) {
            $query->withPivot('quantity', 'beneficiary_number', 'product_variant_id');
        }])->whereIn('id', $request->order_ids)->get();

        $filename = 'orders_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($orders) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Number', 'Volume']);
            
            foreach ($orders as $order) {
                foreach ($order->products as $product) {
                    $size = 'N/A';
                    if ($product->pivot->product_variant_id) {
                        $variant = \App\Models\ProductVariant::find($product->pivot->product_variant_id);
                        if ($variant && isset($variant->variant_attributes['size'])) {
                            $size = preg_replace('/[^0-9.]/', '', $variant->variant_attributes['size']);
                        }
                    }
                    
                    // Format beneficiary number as text to preserve leading zeros
                    $beneficiaryNumber = $product->pivot->beneficiary_number ?? 'N/A';
                    if ($beneficiaryNumber !== 'N/A') {
                        $beneficiaryNumber = "\t" . $beneficiaryNumber;
                    }
                    
                    fputcsv($file, [
                        $beneficiaryNumber,
                        $size
                    ]);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Display AFA orders page.
     */
    public function afaOrders()
    {
        $afaOrders = \App\Models\AFAOrders::with(['afaproduct', 'user'])->latest()->get();
        
        return Inertia::render('Admin/AFAOrders', [
            'afaOrders' => $afaOrders
        ]);
    }

    /**
     * Update AFA order status.
     */
    public function updateAfaOrderStatus(Request $request, \App\Models\AFAOrders $order)
    {
        $request->validate([
            'status' => 'required|string|in:PENDING,COMPLETED,CANCELLED',
        ]);

        $order->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'AFA order status updated successfully.');
    }

    /**
     * Export AFA orders to CSV or Excel.
     */
    public function exportAFAOrders(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:afa_orders,id',
            'format' => 'required|in:csv,excel',
        ]);

        $orders = \App\Models\AFAOrders::whereIn('id', $request->order_ids)->get();

        $filename = 'afa_orders_' . date('Y-m-d_H-i-s');
        
        if ($request->format === 'csv') {
            $filename .= '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function() use ($orders) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Full Name', 'Ghana Card Number', 'Phone', 'Date of Birth', 'Occupation', 'Region']);
                
                foreach ($orders as $order) {
                    fputcsv($file, [
                        $order->full_name,
                        $order->ghana_card_number,
                        $order->phone,
                        $order->dob,
                        $order->occupation,
                        $order->region,
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } else {
            return new \App\Exports\AFAOrdersExport($orders);
        }
    }

    /**
     * Toggle Jaybart order pusher functionality.
     */
    public function toggleJaybartOrderPusher(Request $request)
    {
        $enabled = $request->input('enabled', false);
        Setting::set('jaybart_order_pusher_enabled', $enabled ? '1' : '0');
        
        $status = $enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "Jaybart order pusher {$status} successfully.");
    }

    /**
     * Toggle CodeCraft order pusher functionality.
     */
    public function toggleCodecraftOrderPusher(Request $request)
    {
        $enabled = $request->input('enabled', false);
        Setting::set('codecraft_order_pusher_enabled', $enabled ? '1' : '0');
        
        $status = $enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "CodeCraft order pusher {$status} successfully.");
    }

    /**
     * Toggle DataMaster order pusher functionality.
     */
    public function toggleDatamasterOrderPusher(Request $request)
    {
        $enabled = $request->input('enabled', false);
        Setting::set('datamaster_order_pusher_enabled', $enabled ? '1' : '0');
        
        $status = $enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "DataMaster order pusher {$status} successfully.");
    }

    /**
     * Toggle DataEasy order pusher functionality.
     */
    public function toggleDataeasyOrderPusher(Request $request)
    {
        $enabled = $request->input('enabled', false);
        Setting::set('dataeasy_order_pusher_enabled', $enabled ? '1' : '0');
        
        $status = $enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "DataEasy order pusher {$status} successfully.");
    }

    /**
     * Toggle DataSource order pusher functionality.
     */
    public function toggleDataSourceOrderPusher(Request $request)
    {
        $enabled = $request->input('enabled', false);
        Setting::set('datasource_order_pusher_enabled', $enabled ? '1' : '0');
        
        $status = $enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "DataSource order pusher {$status} successfully.");
    }

    /**
     * Toggle CodeCraft MTN order pusher functionality.
     */
    public function toggleCodecraftMtnOrderPusher(Request $request)
    {
        $enabled = $request->input('enabled', false);
        Setting::set('codecraft_mtn_order_pusher_enabled', $enabled ? '1' : '0');
        
        $status = $enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "CodeCraft MTN order pusher {$status} successfully.");
    }
}
