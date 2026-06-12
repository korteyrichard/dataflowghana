# Finding Your Order Checkout Handler

## Where to Add the MTN Order Pusher Call

The MTN Order Pusher service needs to be called when an order is created. This guide helps you find the right place.

## Step 1: Locate Checkout Controller

### Expected File Locations (in order of likelihood):

1. **app/Http/Controllers/OrdersController.php** (Most likely)
   ```php
   public function checkout(Request $request)
   public function placeOrder(Request $request)
   public function store(Request $request)
   ```

2. **app/Http/Controllers/CheckoutController.php**
   ```php
   public function process(Request $request)
   public function store(Request $request)
   ```

3. **Another custom controller**

### How to Search:

```bash
# Search in your project
grep -r "placeOrder\|checkout" app/Http/Controllers/

# Or look in your routes
grep -r "checkout.process\|place_order" routes/
```

From your `routes/web.php`, I see:
```php
Route::post('/place_order', [OrdersController::class, 'checkout'])->name('checkout.process');
```

**Your checkout handler is likely:** `app/Http/Controllers/OrdersController.php` method `checkout()`

## Step 2: Find the Order Creation Point

Open your checkout handler and look for where the order is created:

### Indicators:
- Line with `Order::create(...)`
- Line with `new Order()`
- Line with `$order->save()`
- After database transaction completes

### Example Pattern:
```php
public function checkout(Request $request)
{
    // Validation
    $request->validate([...]);
    
    // Create order
    $order = Order::create([
        'user_id' => auth()->id(),
        'total' => $request->total,
        'status' => 'pending',
        // ... more fields
    ]);
    
    // ← THIS IS WHERE YOU ADD THE MTN PUSHER CALL
    
    // Attach products
    $order->products()->attach($productIds);
    
    // Return response
    return redirect('/dashboard/orders');
}
```

## Step 3: Check What Products the Order Has

Look for where products are attached or associated with the order:

### Common Patterns:
```php
// Pattern 1: Direct relationship
$order->products()->attach($productIds);
$order->products()->sync($productIds);

// Pattern 2: Through cart
$order->items()->createMany($cartItems);

// Pattern 3: Line items table
OrderItem::create(['order_id' => $order->id, 'product_id' => ...]);
```

## Step 4: Add the MTN Order Pusher Integration

Insert this code **after the order is saved** but **before returning the response**:

### Option A: Simple Integration
```php
use App\Services\MtnOrderPusherService;
use App\Models\Setting;

// After order creation and product attachment:
if (Setting::get('mtn_order_pusher_enabled')) {
    // Check if order contains MTN product (excluding MTN Express)
    $hasMtn = $order->products()->where('name', 'like', '%mtn%')
                    ->where('name', 'not like', '%mtn express%')
                    ->exists();
    
    if ($hasMtn) {
        $pusher = new MtnOrderPusherService();
        $pusher->pushOrderToApi($order);
    }
}
```

### Option B: With Try-Catch (Safer)
```php
use App\Services\MtnOrderPusherService;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

// After order creation:
if (Setting::get('mtn_order_pusher_enabled')) {
    $hasMtn = $order->products()->where('name', 'like', '%mtn%')
                    ->where('name', 'not like', '%mtn express%')
                    ->exists();
    
    if ($hasMtn) {
        try {
            $pusher = new MtnOrderPusherService();
            $pusher->pushOrderToApi($order);
        } catch (\Exception $e) {
            Log::error('MTN Order Pusher failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            // Don't fail the checkout, just log the error
        }
    }
}
```

## Step 5: Complete Example

Here's what a complete checkout method might look like:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Cart;
use App\Services\MtnOrderPusherService;
use App\Models\Setting;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function checkout(Request $request)
    {
        // 1. Validate input
        $request->validate([
            'total' => 'required|numeric|min:0.01',
            'items' => 'required|array|min:1'
        ]);

        // 2. Create order
        $order = Order::create([
            'user_id' => auth()->id(),
            'total' => $request->total,
            'status' => 'pending',
            'network' => $this->detectNetwork($request->items),
            'beneficiary_number' => $this->extractBeneficiary($request->items),
            // ... other fields
        ]);

        // 3. Attach products
        foreach ($request->items as $item) {
            $order->products()->attach($item['product_id'], [
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'beneficiary_number' => $item['beneficiary_number'],
                'product_variant_id' => $item['variant_id']
            ]);
        }

        // 4. PUSH TO MTN ORDER PUSHER (NEW CODE HERE)
        if (Setting::get('mtn_order_pusher_enabled')) {
            $hasMtn = $order->products()
                ->where('name', 'like', '%mtn%')
                ->where('name', 'not like', '%mtn express%')
                ->exists();
            
            if ($hasMtn) {
                try {
                    $pusher = new MtnOrderPusherService();
                    $pusher->pushOrderToApi($order);
                } catch (\Exception $e) {
                    \Log::error('MTN Order Pusher failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // 5. Clear cart
        Cart::where('user_id', auth()->id())->delete();

        // 6. Return response
        return redirect('/dashboard/orders')
            ->with('success', 'Order created successfully');
    }

    // ... other methods ...
}
```

## Step 6: Verify Your Changes

After adding the code, test:

```bash
# 1. Run migration first
php artisan migrate

# 2. Clear cache
php artisan config:cache

# 3. Place a test order with MTN product via UI
# 4. Check if reference_id is set:
php artisan tinker
>>> $order = App\Models\Order::latest()->first()
>>> $order->reference_id  // Should have transaction code
>>> $order->api_status    // Should be 'success'

# 5. Check logs
tail -f storage/logs/laravel.log
```

## Step 7: What to Look For in Existing Code

When examining your checkout handler, look for:

### Imports at the top:
```php
use App\Models\Order;
use App\Models\Cart;
// Add these:
use App\Services\MtnOrderPusherService;
use App\Models\Setting;
```

### Where to Insert Code:
```php
// ✓ BEFORE returning response
// ✓ AFTER order is saved to database
// ✓ AFTER products are attached
// ✗ NOT in try-catch that might fail checkout
// ✗ NOT before order is saved
```

### Example Bad Location (Don't do this):
```php
// ✗ WRONG - Order not saved yet
$order = new Order();
$pusher = new MtnOrderPusherService();
$pusher->pushOrderToApi($order); // order has no ID yet!
$order->save();
```

### Example Good Location (Do this):
```php
// ✓ CORRECT - Order saved, products attached
$order = Order::create([...]);
$order->products()->attach(...);

// NOW push
if (...) {
    $pusher = new MtnOrderPusherService();
    $pusher->pushOrderToApi($order); // order has ID!
}

return redirect(...);
```

## Step 8: Handle Multiple Product Types

If your checkout can have mixed products (MTN and non-MTN), the code already handles it:

```php
// This only pushes if:
// 1. Toggle is enabled
// 2. Order has MTN product
// 3. Order does NOT have MTN Express product

// Mixed order example: [MTN 1GB, MTN 2GB, Telecel]
// Result: Will push (has MTN, no MTN Express)

// Mixed order example: [MTN Express, MTN 1GB]
// Result: Won't push (would be handled by DataMaster)

// Mixed order example: [Telecel, Ishare]
// Result: Won't push (no MTN product)
```

## Troubleshooting

### "Reference ID not set after push"
- Check toggle is enabled: `Setting::get('mtn_order_pusher_enabled')`
- Check order actually has MTN product
- Check logs for API errors

### "Order created but reference_id is null"
- Make sure pusher code is after `Order::create()`
- Check order products are attached
- Verify products have correct names (case-insensitive 'mtn')

### "Checkout fails after adding code"
- Put pusher call in try-catch
- Don't let API errors fail the checkout
- Just log the error instead

## Next Steps

1. ✓ Find your checkout controller
2. ✓ Locate the order creation point
3. ✓ Add the MTN pusher integration
4. ✓ Test with manual order
5. ✓ Verify reference_id is set
6. ✓ Check logs for errors
7. ✓ Deploy to production

## Quick Copy-Paste

Use this snippet (adjust as needed for your code):

```php
// Add at top of file
use App\Services\MtnOrderPusherService;
use App\Models\Setting;

// Add in checkout method (after order creation and product attachment)
if (Setting::get('mtn_order_pusher_enabled')) {
    $hasMtn = $order->products()
        ->where('name', 'like', '%mtn%')
        ->where('name', 'not like', '%mtn express%')
        ->exists();
    
    if ($hasMtn) {
        try {
            (new MtnOrderPusherService())->pushOrderToApi($order);
        } catch (\Exception $e) {
            \Log::error('MTN Order Pusher failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
```

That's it! The service handles the rest.
