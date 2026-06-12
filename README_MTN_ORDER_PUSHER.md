# MTN Order Pusher Service - Complete Implementation

## Quick Summary

A complete MTN-specific order pusher service has been implemented with:
- ✓ Order pushing to Order Pusher API
- ✓ Automatic status syncing from API
- ✓ Admin toggle control (on/off)
- ✓ SMS notifications on completion
- ✓ Comprehensive logging

**Status:** Ready for integration (backend complete, needs checkout integration)

## What's Inside

### Backend Services Created
1. **MtnOrderPusherService** - Pushes orders to API
2. **MtnOrderStatusSyncService** - Syncs statuses from API
3. **Updated OrderStatusSyncService** - Orchestrates all syncs

### Frontend Integration
1. **Admin Dashboard Toggle** - Enable/disable in UI
2. **Routes & Controllers** - Toggle endpoints

### Database
1. **Migration** - Creates mtn_order_pusher_enabled setting

## Documentation Files

| File | Purpose |
|------|---------|
| **MTN_ORDER_PUSHER_SUMMARY.md** | Overview of implementation |
| **MTN_ORDER_PUSHER_IMPLEMENTATION.md** | Technical details & troubleshooting |
| **MTN_ORDER_PUSHER_INTEGRATION.md** | Integration checklist & examples |
| **MTN_ORDER_PUSHER_TASKS.md** | Remaining tasks checklist |
| **FIND_CHECKOUT_HANDLER.md** | How to find & modify checkout |
| **README.md** | This file |

## Quick Start (3 Steps)

### 1. Run Migration
```bash
php artisan migrate
```

### 2. Add to Order Checkout
Find your checkout handler and add this after order creation:
```php
use App\Services\MtnOrderPusherService;
use App\Models\Setting;

if (Setting::get('mtn_order_pusher_enabled')) {
    $hasMtn = $order->products()
        ->where('name', 'like', '%mtn%')
        ->where('name', 'not like', '%mtn express%')
        ->exists();
    
    if ($hasMtn) {
        (new MtnOrderPusherService())->pushOrderToApi($order);
    }
}
```

See **FIND_CHECKOUT_HANDLER.md** for detailed instructions.

### 3. Set Up Status Sync
Add to your scheduler (`app/Console/Kernel.php`):
```php
$schedule->call(function () {
    app(App\Services\OrderStatusSyncService::class)->syncOrderStatuses();
})->everyFiveMinutes();
```

## How It Works

### Order Flow
```
User places order with MTN product
    ↓
Order saved to database (status: 'pending')
    ↓
MTN Order Pusher checks if enabled
    ↓
Order sent to Order Pusher API
    ↓
Transaction code saved as reference_id
    ↓
Order marked as api_status: 'success'
```

### Status Sync Flow
```
Periodic job runs (every 5 minutes)
    ↓
Orders with status 'pending'/'processing' found
    ↓
For MTN orders, API queried for status
    ↓
Order status updated if changed
    ↓
SMS sent to user if completed
```

## Configuration

### API Endpoints
Already configured in `.env`:
```
ORDER_PUSHER_BASE_URL=https://agent.jaybartservices.com/api/v1
ORDER_PUSHER_API_KEY=534c0aab68d0e52e28e06e2d452de15846de3723
```

### Admin Toggle
- **Location:** Admin Dashboard → System Controls
- **Default:** Enabled
- **Persists:** To database settings

### Enable/Disable Programmatically
```php
// Disable
Setting::set('mtn_order_pusher_enabled', '0');

// Enable
Setting::set('mtn_order_pusher_enabled', '1');

// Check
if (Setting::get('mtn_order_pusher_enabled')) {
    // enabled
}
```

## Files Modified/Created

### Created Files
- `app/Services/MtnOrderPusherService.php`
- `app/Services/MtnOrderStatusSyncService.php`
- `database/migrations/2026_01_10_000001_add_mtn_order_pusher_setting.php`

### Modified Files
- `app/Services/OrderStatusSyncService.php`
- `app/Http/Controllers/AdminDashboardController.php`
- `routes/web.php`
- `resources/js/pages/Admin/Dashboard.tsx`

## API Integration

### Order Push
```
POST /api/v1/buy-other-package
Headers: x-api-key: {...}
Body:
  - recipient_msisdn (phone)
  - network_id: 3
  - shared_bundle (MB)
Response:
  - success: true/false
  - transaction_code: "TXN..."
```

### Status Check
```
POST /api/v1/order/bulk/status
Headers: X-API-KEY: {...}
Body:
  - orderid (reference_id)
  - data_size: 1
Response:
  - success: true/false
  - recored: [{status: "...", ...}]
```

## Status Mapping

| External | Internal |
|----------|----------|
| successful | completed |
| completed | completed |
| processing | processing |
| pending | processing |
| pending2 | processing |
| failed | cancelled |
| cancelled | cancelled |

## Testing

### Manual Order Test
1. Go to checkout
2. Create order with MTN product
3. Check database:
   - `reference_id` should have transaction code
   - `api_status` should be 'success'
4. Check logs: `storage/logs/laravel.log`

### Manual Sync Test
```bash
php artisan tinker
>>> app(App\Services\OrderStatusSyncService::class)->syncOrderStatuses()
# Check logs for output
```

### Admin Toggle Test
1. Admin Dashboard → System Controls
2. Toggle MTN Order Pusher ON/OFF
3. Verify setting changes in database

## Troubleshooting

### Orders Not Pushing
- Check toggle is enabled
- Check product name contains 'mtn' (not 'mtn express')
- Check logs for API errors

### Status Not Syncing
- Check order has reference_id
- Check scheduler/queue is running
- Check logs for API errors

### Checkout Failing
- Check migration ran: `php artisan migrate`
- Check code added in safe location (try-catch)
- Check logs for errors

See **MTN_ORDER_PUSHER_IMPLEMENTATION.md** for detailed troubleshooting.

## Logging

All operations logged to `storage/logs/laravel.log`:

```bash
# View MTN-specific logs
tail -f storage/logs/laravel.log | grep -i mtn

# View order push logs
grep "Order pushed to MTN" storage/logs/laravel.log

# View status sync logs
grep "MTN order status" storage/logs/laravel.log
```

## Key Features

✓ **Selective:** Only handles MTN products (excludes MTN Express)
✓ **Toggleable:** Can enable/disable via admin dashboard
✓ **Automatic:** Status syncs periodically
✓ **Notified:** SMS sent when order completed
✓ **Safe:** Try-catch prevents checkout failures
✓ **Logged:** All operations logged for debugging
✓ **Formatted:** Phone numbers auto-formatted

## Architecture

### Service Layer
- **MtnOrderPusherService** - Order push logic
- **MtnOrderStatusSyncService** - Status check logic
- **OrderStatusSyncService** - Orchestrator

### Controller Layer
- **AdminDashboardController** - Toggle endpoint

### Frontend
- **Admin Dashboard** - Toggle UI

### Database
- **Settings Table** - Stores mtn_order_pusher_enabled

## Next Steps

1. Read **FIND_CHECKOUT_HANDLER.md** to find your checkout controller
2. Add MTN pusher code to checkout (after order creation)
3. Run migration: `php artisan migrate`
4. Configure scheduler/queue for status sync
5. Test with manual order
6. Monitor logs
7. Deploy to production

## Integration Checklist

- [ ] Read FIND_CHECKOUT_HANDLER.md
- [ ] Locate checkout handler in your code
- [ ] Add imports (MtnOrderPusherService, Setting)
- [ ] Add pusher call (after order creation)
- [ ] Run migration: `php artisan migrate`
- [ ] Configure scheduler or queue
- [ ] Test order creation
- [ ] Verify reference_id saved
- [ ] Check logs for errors
- [ ] Test admin toggle
- [ ] Test status sync
- [ ] Deploy to staging
- [ ] Final testing
- [ ] Deploy to production

## Support

### Documentation
- Quick start above
- **MTN_ORDER_PUSHER_SUMMARY.md** - Implementation overview
- **MTN_ORDER_PUSHER_IMPLEMENTATION.md** - Technical guide
- **MTN_ORDER_PUSHER_INTEGRATION.md** - Integration guide
- **FIND_CHECKOUT_HANDLER.md** - Code location guide

### Logs
- Check: `storage/logs/laravel.log`
- Filter for "mtn" or "MTN"

### Debugging
```bash
# Check migration applied
php artisan tinker
>>> App\Models\Setting::where('key', 'mtn_order_pusher_enabled')->first()

# Check order after push
>>> $order = App\Models\Order::latest()->first()
>>> $order->reference_id
>>> $order->api_status

# Manually sync
>>> app(App\Services\OrderStatusSyncService::class)->syncOrderStatuses()
```

## Implementation Status

| Component | Status | Notes |
|-----------|--------|-------|
| Services | ✓ Done | MtnOrderPusherService, MtnOrderStatusSyncService |
| Controller | ✓ Done | toggleMtnOrderPusher method |
| Routes | ✓ Done | /admin/toggle-mtn-order-pusher |
| Frontend | ✓ Done | Admin Dashboard toggle |
| Migration | ✓ Done | Creates setting |
| **Order Checkout** | ⚠️ TODO | Add pusher call to checkout |
| **Status Sync** | ⚠️ TODO | Configure scheduler/queue |
| **Testing** | ⚠️ TODO | Manual testing needed |

## Production Readiness

✓ Code complete
✓ Error handling implemented
✓ Logging comprehensive
⚠️ Needs checkout integration
⚠️ Needs scheduler/queue setup
⚠️ Needs testing before deployment

## Version Info

- **Created:** 2026-01-10
- **Laravel Version:** 11+ (uses modern syntax)
- **PHP Version:** 8.0+
- **Database:** MySQL/MariaDB

## License

Same as your application

## Questions?

Refer to the appropriate documentation:
- **How to integrate?** → FIND_CHECKOUT_HANDLER.md
- **How does it work?** → MTN_ORDER_PUSHER_IMPLEMENTATION.md
- **What's remaining?** → MTN_ORDER_PUSHER_TASKS.md
- **Full reference?** → MTN_ORDER_PUSHER_SUMMARY.md

---

**Ready to integrate? Start with FIND_CHECKOUT_HANDLER.md**
