# MTN Order Pusher - Integration Checklist

## Quick Start

The MTN Order Pusher service is ready to use. You need to integrate it into your order processing flow.

## Integration Points

### 1. Order Checkout (Where Orders are Created)

**Find Your Checkout Handler:**
Location likely: `app/Http/Controllers/OrdersController.php` (method: `checkout` or similar)

**Add This Logic:**
```php
use App\Services\MtnOrderPusherService;
use App\Models\Setting;

// After order is created and saved to database
if ($order->products->contains(function($product) {
    return stripos($product->name, 'mtn') !== false && 
           stripos($product->name, 'mtn express') === false;
})) {
    // This is an MTN order (but not MTN Express)
    if (Setting::get('mtn_order_pusher_enabled', '1')) {
        $pusher = new MtnOrderPusherService();
        $pusher->pushOrderToApi($order);
    }
}

// Continue with rest of checkout logic...
```

**Alternative (Using Dependency Injection):**
```php
use App\Services\MtnOrderPusherService;
use App\Models\Setting;

public function checkout(Request $request, MtnOrderPusherService $pusher)
{
    // ... create order ...
    
    // Check if it's an MTN order (non-Express)
    $hasMtnOrder = $order->products->contains(function($product) {
        return stripos($product->name, 'mtn') !== false && 
               stripos($product->name, 'mtn express') === false;
    });
    
    if ($hasMtnOrder && Setting::get('mtn_order_pusher_enabled', '1')) {
        $pusher->pushOrderToApi($order);
    }
    
    // ... continue ...
}
```

### 2. Order Status Sync (Already Integrated ✓)

**Location:** `app/Services/OrderStatusSyncService.php` (line ~31)

**Already Done:**
✓ MtnOrderStatusSyncService is instantiated and called
✓ MTN orders are properly filtered and synced
✓ No additional integration needed

### 3. Admin Dashboard (Already Integrated ✓)

**Location:** Admin Dashboard page

**Already Done:**
✓ Toggle appears in System Controls section
✓ Can enable/disable MTN order pusher
✓ Persists to database settings
✓ No additional integration needed

## What Gets Set When Pushed

When an order is successfully pushed to MTN Order Pusher API:

| Field | Value |
|-------|-------|
| `reference_id` | Transaction code from API |
| `api_status` | 'success' or 'failed' |
| `status` | Remains as-is (user sets this) |

Example after push:
```php
$order->reference_id = "TXN123456789";  // From API
$order->api_status = "success";          // or "failed"
```

## Status Sync Behavior

Status sync runs periodically (configure in your job scheduler):

**Orders Synced:**
- Status is 'pending' or 'processing'
- Has reference_id set (from push)
- Contains MTN product (but not MTN Express)

**When Synced:**
- API is queried for latest status
- Internal status updated if changed
- SMS sent to user if order completed

## Example Workflow

```
1. User places order with MTN product
   ↓
2. Order saved to database with initial status 'pending'
   ↓
3. MTN Order Pusher checks if enabled (toggle)
   ↓
4. If enabled, pushes to Order Pusher API
   ↓
5. API returns transaction code
   ↓
6. Order.reference_id set to transaction code
   ↓
7. Order.api_status set to 'success'
   ↓
8. [Later] Status sync job runs
   ↓
9. Queries API: "What's status of TXN123456789?"
   ↓
10. API responds with current status
    ↓
11. Order.status updated (e.g., 'pending' → 'processing')
    ↓
12. If completed, SMS sent to user
```

## Testing Integration

### Manual Test

```php
// Run in tinker: php artisan tinker

$order = App\Models\Order::find(1);
$pusher = new App\Services\MtnOrderPusherService();
$pusher->pushOrderToApi($order);

// Check result
$order->refresh();
dd($order->reference_id, $order->api_status);
```

### Status Sync Test

```php
// In tinker
$sync = new App\Services\MtnOrderStatusSyncService(
    app(App\Services\MoolreSmsService::class)
);
$sync->syncOrderStatuses();

// Check logs
tail -f storage/logs/laravel.log
```

## Common Issues & Solutions

### Issue: Orders Not Pushing
**Check:**
1. Is the toggle enabled? `Setting::get('mtn_order_pusher_enabled')`
2. Is your code calling the pusher service?
3. Does order contain MTN product? (not MTN Express)
4. Check logs for API errors

### Issue: Status Not Updating
**Check:**
1. Does order have reference_id? (from push)
2. Is order status 'pending' or 'processing'?
3. Does order contain MTN product?
4. Check API credentials in .env
5. Review logs for sync errors

### Issue: Wrong Statuses Being Used
**Check:**
1. Are you checking for MTN Express correctly?
2. Verify status mapping in MtnOrderStatusSyncService
3. Check external API response status value

## Configuration

### Disable/Enable
```php
// Disable
Setting::set('mtn_order_pusher_enabled', '0');

// Enable
Setting::set('mtn_order_pusher_enabled', '1');

// Check status
if (Setting::get('mtn_order_pusher_enabled')) {
    // enabled
}
```

### Using Admin Dashboard
1. Go to Admin Dashboard
2. Find "MTN Order Pusher" in System Controls
3. Toggle ON/OFF
4. Settings persisted automatically

## Database Fields Used

Make sure your orders table has:
- `reference_id` (string, nullable) - stores transaction code
- `api_status` (string, nullable) - stores 'success' or 'failed'
- `status` (string) - stores order status ('pending', 'processing', 'completed', 'cancelled')

Migration already present: `2025_10_10_140000_add_api_status_to_orders_table.php`

## Logs Location

All activity logged to:
```
storage/logs/laravel.log
```

Filter for MTN logs:
```bash
grep -i "mtn" storage/logs/laravel.log
```

## Next Steps

1. ✓ Review MTN_ORDER_PUSHER_IMPLEMENTATION.md for full details
2. ✓ Locate your order checkout handler
3. ✓ Add MTN pusher integration (see Integration Points #1 above)
4. ✓ Test with manual order
5. ✓ Monitor logs: `tail -f storage/logs/laravel.log`
6. ✓ Verify orders have reference_id after push
7. ✓ Run status sync and verify updates
8. ✓ Set up scheduler for periodic status sync

## Files to Check

| File | Purpose | Status |
|------|---------|--------|
| `app/Services/MtnOrderPusherService.php` | Order pushing logic | ✓ Created |
| `app/Services/MtnOrderStatusSyncService.php` | Status syncing logic | ✓ Created |
| `app/Services/OrderStatusSyncService.php` | Main sync orchestrator | ✓ Updated |
| `app/Http/Controllers/AdminDashboardController.php` | Admin controllers | ✓ Updated |
| `routes/web.php` | Route definitions | ✓ Updated |
| `resources/js/pages/Admin/Dashboard.tsx` | Admin UI | ✓ Updated |
| `database/migrations/2026_01_10_000001_add_mtn_order_pusher_setting.php` | Settings migration | ✓ Created |

## Support

For issues:
1. Check logs: `storage/logs/laravel.log`
2. Review API response in logs
3. Verify phone number formatting
4. Check product variants have size attribute
5. Ensure order has beneficiary_number
