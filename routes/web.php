<?php

use App\Http\Controllers\BecomeAgentController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\JoinUsController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AFAController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\TermsController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/become_an_agent', function () {
        return Inertia::render('become_an_agent');
    })->name('become_an_agent');

Route::middleware(['auth'])->group(function () {
    Route::post('/become_an_agent', [BecomeAgentController::class, 'update'])->name('become_an_agent.update');
});
Route::get('/agent/callback', [BecomeAgentController::class, 'handleAgentCallback'])->name('agent.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/wallet', [WalletController::class, 'index'])->name('dashboard.wallet');
    Route::get('/dashboard/joinUs', [JoinUsController::class, 'index'])->name('dashboard.joinUs');
    Route::get('/dashboard/orders', [OrdersController::class, 'index'])->name('dashboard.orders');
    Route::get('/dashboard/transactions', [TransactionsController::class, 'index'])->name('dashboard.transactions');
    Route::get('/dashboard/afa-registration', [AFAController::class, 'index'])->name('dashboard.afa');
    Route::post('/dashboard/afa-registration', [AFAController::class, 'store'])->name('dashboard.afa.store');
    Route::get('/dashboard/afa-orders', [AFAController::class, 'afaOrders'])->name('dashboard.afa.orders');
    Route::get('/dashboard/api-docs', [ApiDocsController::class, 'index'])->name('dashboard.api-docs');
    Route::get('/dashboard/terms', [TermsController::class, 'index'])->name('dashboard.terms');

    // Cart routes
    Route::post('/add-to-cart', [CartController::class, 'store'])->name('add.to.cart');
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::delete('/cart/{cart}', [CartController::class, 'destroy'])->name('remove.from.cart');
    Route::post('/process-excel-to-cart', [CartController::class, 'processExcelToCart']);
    Route::post('/process-bulk-to-cart', [CartController::class, 'processBulkToCart']);

    // Wallet balance route
    Route::post('/dashboard/wallet/add', [DashboardController::class, 'addToWallet'])->name('dashboard.wallet.add');
    Route::get('/wallet/callback', [DashboardController::class, 'handleWalletCallback'])->name('wallet.callback');
    Route::post('/verify-payment', [WalletController::class, 'verifyPayment'])->name('verify.payment');

    
    // Bundle sizes API
    Route::get('/api/bundle-sizes', [DashboardController::class, 'getBundleSizes'])->name('api.bundle-sizes');

    // ❌ REMOVED THE DUPLICATE ADMIN ROUTE FROM HERE
    // Route::get('/admin/dashboard', [\App\Http\Controllers\AdminDashboardController::class, 'index'])->name('admin.dashboard');
});

// Checkout route
Route::middleware(['auth'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/place_order', [OrdersController::class, 'checkout'])->name('checkout.process');
});

// Admin routes - This is the correct group with role middleware
Route::middleware(['auth', 'verified', 'role:admin'])->name('admin.')->group(function () {
    Route::get('admin/dashboard', [\App\Http\Controllers\AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('admin/users', [\App\Http\Controllers\AdminDashboardController::class, 'users'])->name('users');
    Route::post('admin/users', [\App\Http\Controllers\AdminDashboardController::class, 'storeUser'])->name('users.store');
    Route::put('admin/users/{user}', [\App\Http\Controllers\AdminDashboardController::class, 'updateUserRole'])->name('users.updateRole');
    Route::delete('admin/users/{user}', [\App\Http\Controllers\AdminDashboardController::class, 'deleteUser'])->name('users.delete');
    Route::post('admin/users/{user}/credit', [\App\Http\Controllers\AdminDashboardController::class, 'creditWallet'])->name('users.credit');
    Route::post('admin/users/{user}/debit', [\App\Http\Controllers\AdminDashboardController::class, 'debitWallet'])->name('users.debit');
    Route::get('admin/products', [\App\Http\Controllers\AdminDashboardController::class, 'products'])->name('products');
    Route::post('admin/products', [\App\Http\Controllers\AdminDashboardController::class, 'storeProduct'])->name('products.store');
    Route::put('admin/products/{product}', [\App\Http\Controllers\AdminDashboardController::class, 'updateProduct'])->name('products.update');
    Route::delete('admin/products/{product}', [\App\Http\Controllers\AdminDashboardController::class, 'deleteProduct'])->name('products.delete');
    Route::get('admin/variations', [\App\Http\Controllers\Admin\VariationAttributeController::class, 'index'])->name('variations');
    Route::post('admin/variation-attributes', [\App\Http\Controllers\Admin\VariationAttributeController::class, 'store'])->name('variation-attributes.store');
    Route::put('admin/variation-attributes/{variationAttribute}', [\App\Http\Controllers\Admin\VariationAttributeController::class, 'update'])->name('variation-attributes.update');
    Route::delete('admin/variation-attributes/{variationAttribute}', [\App\Http\Controllers\Admin\VariationAttributeController::class, 'destroy'])->name('variation-attributes.delete');
    Route::get('admin/orders', [\App\Http\Controllers\AdminDashboardController::class, 'orders'])->name('orders');
    Route::delete('admin/orders/{order}', [\App\Http\Controllers\AdminDashboardController::class, 'deleteOrder'])->name('orders.delete');
    Route::put('admin/orders/{order}/status', [\App\Http\Controllers\AdminDashboardController::class, 'updateOrderStatus'])->name('orders.updateStatus');
    Route::put('admin/orders/bulk-status', [\App\Http\Controllers\AdminDashboardController::class, 'bulkUpdateOrderStatus'])->name('orders.bulkUpdateStatus');
    Route::get('admin/transactions', [\App\Http\Controllers\AdminDashboardController::class, 'transactions'])->name('transactions');
    Route::get('admin/users/{user}/transactions', [\App\Http\Controllers\AdminDashboardController::class, 'userTransactions'])->name('users.transactions');
    Route::post('admin/orders/export', [\App\Http\Controllers\AdminDashboardController::class, 'exportOrders'])->name('orders.export');
    Route::get('admin/afa-products', [\App\Http\Controllers\Admin\AFAProductController::class, 'index'])->name('afa-products');
    Route::post('admin/afa-products', [\App\Http\Controllers\Admin\AFAProductController::class, 'store'])->name('afa-products.store');
    Route::put('admin/afa-products/{afaProduct}', [\App\Http\Controllers\Admin\AFAProductController::class, 'update'])->name('afa-products.update');
    Route::delete('admin/afa-products/{afaProduct}', [\App\Http\Controllers\Admin\AFAProductController::class, 'destroy'])->name('afa-products.destroy');
    Route::get('admin/afa-orders', [\App\Http\Controllers\AdminDashboardController::class, 'afaOrders'])->name('afa-orders');
    Route::put('admin/afa-orders/{order}/status', [\App\Http\Controllers\AdminDashboardController::class, 'updateAfaOrderStatus'])->name('afa.orders.updateStatus');
    Route::post('admin/toggle-jaybart-order-pusher', [\App\Http\Controllers\AdminDashboardController::class, 'toggleJaybartOrderPusher'])->name('toggle.jaybart.order.pusher');
    Route::post('admin/toggle-codecraft-order-pusher', [\App\Http\Controllers\AdminDashboardController::class, 'toggleCodecraftOrderPusher'])->name('toggle.codecraft.order.pusher');
});

// Paystack payment routes
Route::get('/payment', function () {
    return view('payment');
})->name('payment');
Route::post('/payment/initialize', [PaymentController::class, 'initializePayment'])->name('payment.initialize');
Route::get('/payment/callback', [PaymentController::class, 'handleCallback'])->name('payment.callback');
Route::get('/payment/success', function () { return 'Payment Successful!'; })->name('payment.success');
Route::get('/payment/failed', function () { return 'Payment Failed!'; })->name('payment.failed');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';