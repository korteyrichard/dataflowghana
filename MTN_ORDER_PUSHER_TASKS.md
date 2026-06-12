# MTN Order Pusher - Remaining Tasks Checklist

## ✓ Completed Tasks

- [x] Created MtnOrderPusherService.php
- [x] Created MtnOrderStatusSyncService.php
- [x] Updated OrderStatusSyncService.php
- [x] Updated AdminDashboardController.php
- [x] Added toggle route in routes/web.php
- [x] Updated Admin Dashboard UI (Dashboard.tsx)
- [x] Created migration for mtn_order_pusher_enabled setting
- [x] Created comprehensive documentation

## ⚠️ Remaining Tasks (Required for Full Integration)

### 1. Run Database Migration
**Status:** NOT YET DONE
**Command:**
```bash
php artisan migrate
```
**Verification:**
```bash
php artisan tinker
>>> App\Models\Setting::where('key', 'mtn_order_pusher_enabled')->first()
```

### 2. Integrate MTN Pusher into Order Checkout
**Status:** NOT YET DONE - CRITICAL
**Location:** Find your order checkout handler
**Typical Location:** `app/Http/Controllers/OrdersController.php` (method: `checkout`)

**Add This Code:**
```php
use App\Services\MtnOrderPusherService;
use App\Models\Setting;

// After creating and saving the order:
$hasMtnProduct = $order->products()->where('name', 'like', '%mtn%')->exists() &&
                 !$order->products()->where('name', 'like', '%mtn express%')->exists();

if ($hasMtnProduct && Setting::get('mtn_order_pusher_enabled')) {
    $pusher = new MtnOrderPusherService();
    $pusher->pushOrderToApi($order);
}
```

### 3. Set Up Periodic Status Sync
**Status:** NOT YET DONE - Important
**Option A: Via Scheduler (Recommended)**
Edit: `app/Console/Kernel.php`
```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $sync = app(App\Services\OrderStatusSyncService::class);
        $sync->syncOrderStatuses();
    })->everyFiveMinutes();
}
```

**Option B: Via Queue Job**
Create: `app/Jobs/SyncOrderStatuses.php`
```php
use App\Services\OrderStatusSyncService;

class SyncOrderStatuses implements ShouldQueue
{
    public function handle(OrderStatusSyncService $sync)
    {
        $sync->syncOrderStatuses();
    }
}
```

### 4. Configure Queue (If Using Queue Job)
**Status:** Conditional
**If you chose Option B (Queue), configure:**
- Update `.env` QUEUE_CONNECTION
- Run: `php artisan queue:listen`

### 5. Test Order Push
**Status:** NOT YET DONE - Testing
**Steps:**
1. Go to Admin Dashboard
2. Verify MTN Order Pusher toggle is visible
3. Create test order with MTN product
4. Check order table:
   - `reference_id` should have transaction code
   - `api_status` should be 'success'
5. Check logs: `storage/logs/laravel.log`

### 6. Test Status Sync
**Status:** NOT YET DONE - Testing
**Steps:**
1. Manually trigger sync:
   ```bash
   php artisan tinker
   >>> app(App\Services\OrderStatusSyncService::class)->syncOrderStatuses()
   ```
2. Check logs for MTN sync activity
3. Verify order status updated
4. Check SMS sent to user

### 7. Verify Admin Toggle Works
**Status:** NOT YET DONE - Testing
**Steps:**
1. Go to Admin Dashboard
2. Find "MTN Order Pusher" in System Controls
3. Toggle ON and OFF
4. Verify setting changes:
   ```bash
   php artisan tinker
   >>> App\Models\Setting::get('mtn_order_pusher_enabled')
   ```

### 8. Handle Existing Orders (Optional)
**Status:** NOT YET DONE - Optional
**If you want to push existing orders:**
```bash
php artisan tinker

$orders = App\Models\Order::where('status', 'pending')
    ->whereHas('products', function($q) {
        $q->where('name', 'like', '%mtn%')
          ->where('name', 'not like', '%mtn express%');
    })
    ->get();

$pusher = new App\Services\MtnOrderPusherService();
foreach ($orders as $order) {
    $pusher->pushOrderToApi($order);
    echo "Pushed order {$order->id}\n";
}
```

## 🚀 Deployment Checklist

Before going to production:

- [ ] Migration run in development: `php artisan migrate`
- [ ] Order push integration added to checkout
- [ ] Status sync scheduled or queued
- [ ] Tested with manual order
- [ ] Logs reviewed for errors
- [ ] Admin toggle verified working
- [ ] SMS notifications tested
- [ ] Performance impact assessed
- [ ] Documentation reviewed by team
- [ ] Backup of database created
- [ ] Deploy to staging
- [ ] Final testing in staging
- [ ] Deploy to production
- [ ] Monitor logs in production
- [ ] Notify support team

## 📋 Integration Checklist

### Your Order Checkout Flow

**File to Update:** (Find your checkout handler)
- [ ] Located checkout handler file
- [ ] Added MtnOrderPusherService import
- [ ] Added Setting import
- [ ] Added MTN detection logic
- [ ] Added pusher call
- [ ] Tested order creation
- [ ] Verified reference_id saved
- [ ] Checked logs

### Scheduler/Queue Setup

- [ ] Chose scheduler or queue
- [ ] Updated Kernel.php (if scheduler)
- [ ] Created Job (if queue)
- [ ] Configured QUEUE_CONNECTION (if queue)
- [ ] Tested sync runs
- [ ] Verified logs output

### Testing

- [ ] Manual order test
- [ ] Admin toggle test
- [ ] Status sync test
- [ ] SMS notification test
- [ ] Error handling test
- [ ] Phone formatting test

## 🔍 Verification Steps

After each task, verify:

```bash
# Check migration applied
php artisan tinker
>>> App\Models\Setting::where('key', 'mtn_order_pusher_enabled')->first()

# Check services exist
>>> class_exists('App\Services\MtnOrderPusherService')
=> true

# Check controller method exists
>>> method_exists('App\Http\Controllers\AdminDashboardController', 'toggleMtnOrderPusher')
=> true

# Check route exists
>>> Illuminate\Support\Facades\Route::has('admin.toggle.mtn.order.pusher')
=> true
```

## 🐛 Common Issues to Watch For

1. **Orders not pushing**
   - Check toggle is enabled
   - Check product is MTN (not MTN Express)
   - Check beneficiary_number exists
   - Check logs for API errors

2. **Status not syncing**
   - Check order has reference_id
   - Check scheduler/queue is running
   - Check API credentials
   - Check logs for API errors

3. **Wrong statuses**
   - Check status mapping in service
   - Check API response format
   - Review logs for status values

4. **SMS not sending**
   - Check Moolre SMS configured
   - Check user has phone number
   - Check logs for SMS errors

## 📞 Support Resources

1. **Documentation:**
   - MTN_ORDER_PUSHER_SUMMARY.md (overview)
   - MTN_ORDER_PUSHER_IMPLEMENTATION.md (technical)
   - MTN_ORDER_PUSHER_INTEGRATION.md (integration)

2. **Code Reference:**
   - MtnOrderPusherService.php (order push)
   - MtnOrderStatusSyncService.php (status sync)
   - OrderStatusSyncService.php (orchestrator)
   - AdminDashboardController.php (toggle)

3. **Logs Location:**
   - storage/logs/laravel.log (search for "mtn" or "MTN")

## ✅ Final Checklist

When you complete all tasks:

- [ ] All code deployed
- [ ] Migration applied
- [ ] Checkout integration done
- [ ] Scheduler/queue configured
- [ ] All tests passed
- [ ] Documentation understood
- [ ] Team notified
- [ ] Production deployed
- [ ] Logs monitored

## 📝 Notes

**Important:** The implementation is complete on the backend. You must:
1. Run the migration
2. Add the order push call to your checkout handler
3. Set up periodic status sync
4. Test everything

**Questions:** Refer to documentation files or check service code for details.

**Support:** Check logs at `storage/logs/laravel.log` for detailed error messages.

---

**Status:** Implementation ready for integration
**Priority:** HIGH - Complete tasks in order
**Estimated Time:** 30-60 minutes for full integration
