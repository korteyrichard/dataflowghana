# MTN Order Pusher - Visual Quick Reference

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    USER INTERFACE                            │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌────────────────────────────────────────────────────────┐ │
│  │          Admin Dashboard (Dashboard.tsx)               │ │
│  │  ┌──────────────────────────────────────────────────┐ │ │
│  │  │ System Controls                                  │ │ │
│  │  │ ├─ Jaybart Order Pusher      [Toggle ON/OFF] ◎ │ │ │
│  │  │ ├─ CodeCraft Order Pusher    [Toggle ON/OFF] ◎ │ │ │
│  │  │ ├─ DataMaster Order Pusher   [Toggle ON/OFF] ◎ │ │ │
│  │  │ ├─ DataEasy Order Pusher     [Toggle ON/OFF] ◎ │ │ │
│  │  │ └─ MTN Order Pusher          [Toggle ON/OFF] ◎ │ │ │  ← NEW
│  │  └──────────────────────────────────────────────────┘ │ │
│  └────────────────────────────────────────────────────────┘ │
│                          ↑                                   │
└──────────────────────────┼───────────────────────────────────┘
                           │ (Inertia Props)
                           │
                    ┌──────┴──────┐
                    │             │
        ┌───────────▼──┐   ┌──────▼─────────┐
        │   Route:     │   │  Controller:   │
        │ POST toggle- │   │ toggleMtn      │
        │ mtn-order-   │   │ OrderPusher()  │
        │ pusher       │   │                │
        └──────────────┘   └──────┬─────────┘
                                  │
                                  ▼
                       ┌──────────────────────┐
                       │ Setting::set()       │
                       │ Store: DB Settings   │
                       └──────────────────────┘
```

## Order Push Flow

```
┌─────────────────────────────────────────────────────────────┐
│                   ORDER CHECKOUT                             │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│ 1. User submits order with MTN product                      │
│    ↓                                                          │
│ 2. OrdersController::checkout() called                      │
│    ↓                                                          │
│ 3. Order created & saved to DB                             │
│    ├─ id, user_id, status, total, etc.                     │
│    └─ reference_id: NULL, api_status: NULL                 │
│    ↓                                                          │
│ 4. Products attached to order                              │
│    ├─ product_id, quantity, beneficiary_number, etc.      │
│    └─ product_variant_id for size info                     │
│    ↓                                                          │
│ 5. NEW: Check if MTN Order Pusher enabled                  │
│    ├─ if (Setting::get('mtn_order_pusher_enabled'))        │
│    └─ if (order has MTN product && !MTN Express)          │
│    ↓                                                          │
│ 6. MtnOrderPusherService::pushOrderToApi()                 │
│    ├─ Extract: phone, data_size, network_id                │
│    ├─ Format: phone to 10-digit format                     │
│    └─ Create: POST request to Order Pusher API             │
│    ↓                                                          │
│ 7. Order Pusher API response                               │
│    ├─ Success: {success: true, transaction_code: "TXN..."} │
│    └─ Error: {success: false, message: "..."}              │
│    ↓                                                          │
│ 8. Update order in DB                                      │
│    ├─ On success:                                           │
│    │  └─ reference_id = "TXN...", api_status = "success"   │
│    └─ On error:                                             │
│       └─ api_status = "failed"                             │
│    ↓                                                          │
│ 9. Return to checkout success page                         │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## Status Sync Flow

```
┌─────────────────────────────────────────────────────────────┐
│           PERIODIC STATUS SYNC (Every 5 minutes)            │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│ 1. Scheduler/Queue runs OrderStatusSyncService             │
│    ↓                                                          │
│ 2. Find all 'pending' and 'processing' orders              │
│    ↓                                                          │
│ 3. For each order, determine type:                         │
│    ├─ MTN Express? → DataMasterOrderStatusSyncService      │
│    ├─ MTN (other)? → MtnOrderStatusSyncService             │ ← NEW
│    ├─ DataEasy? → DataEasyStatusSyncService                │
│    ├─ Telecel/Ishare/Bigtime? → CodeCraftSync             │
│    └─ Other? → Skip                                         │
│    ↓                                                          │
│ 4. For MTN orders (MtnOrderStatusSyncService):             │
│    ├─ Filter: has reference_id && status pending/proc.    │
│    ├─ Query: POST /order/bulk/status                       │
│    │   Body: {orderid: reference_id, data_size: 1}         │
│    └─ Headers: X-API-KEY: {...}                           │
│    ↓                                                          │
│ 5. Order Pusher API response                               │
│    ├─ Success: {success: true, recored: [{status: "..."}]} │
│    └─ Error: {success: false, message: "..."}              │
│    ↓                                                          │
│ 6. Status mapping                                          │
│    ├─ successful → completed                              │
│    ├─ completed → completed                               │
│    ├─ processing → processing                             │
│    ├─ pending → processing                                │
│    ├─ failed → cancelled                                  │
│    └─ cancelled → cancelled                               │
│    ↓                                                          │
│ 7. If status changed:                                      │
│    ├─ Update: order.status = new_status                   │
│    └─ If completed: Send SMS notification                 │
│    ↓                                                          │
│ 8. Log all changes                                         │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## File Structure

```
dataflow/
├── app/
│   ├── Services/
│   │   ├── MtnOrderPusherService.php              ← NEW
│   │   ├── MtnOrderStatusSyncService.php          ← NEW
│   │   ├── OrderStatusSyncService.php             ← UPDATED
│   │   ├── DataMasterOrderStatusSyncService.php   (existing)
│   │   ├── DataEasyStatusSyncService.php          (existing)
│   │   ├── CodeCraftOrderPusherService.php        (existing)
│   │   └── MoolreSmsService.php                   (existing)
│   │
│   ├── Http/
│   │   └── Controllers/
│   │       └── AdminDashboardController.php       ← UPDATED
│   │           ├── toggleJaybartOrderPusher()     (existing)
│   │           ├── toggleCodecraftOrderPusher()   (existing)
│   │           ├── toggleDatamasterOrderPusher()  (existing)
│   │           ├── toggleDataeasyOrderPusher()    (existing)
│   │           └── toggleMtnOrderPusher()         ← NEW
│   │
│   └── Models/
│       └── Order.php                              (existing)
│
├── routes/
│   └── web.php                                    ← UPDATED
│       └── POST /admin/toggle-mtn-order-pusher
│
├── resources/
│   └── js/
│       └── pages/
│           └── Admin/
│               └── Dashboard.tsx                  ← UPDATED
│                   └── MTN Order Pusher toggle UI ← NEW
│
├── database/
│   └── migrations/
│       └── 2026_01_10_000001_add_mtn_order_pusher_setting.php  ← NEW
│
├── storage/
│   └── logs/
│       └── laravel.log (check here for debug info)
│
└── Documentation (NEW)
    ├── README_MTN_ORDER_PUSHER.md
    ├── MTN_ORDER_PUSHER_SUMMARY.md
    ├── MTN_ORDER_PUSHER_IMPLEMENTATION.md
    ├── MTN_ORDER_PUSHER_INTEGRATION.md
    ├── MTN_ORDER_PUSHER_TASKS.md
    └── FIND_CHECKOUT_HANDLER.md
```

## Database Schema (Existing)

```sql
orders table:
├── id (primary key)
├── user_id (foreign key)
├── status (pending, processing, completed, cancelled)
├── reference_id (← Transaction code stored here) ✓
├── api_status (success, failed)
├── total
├── network
├── beneficiary_number
└── ... other fields

order_product (pivot table):
├── order_id (foreign key)
├── product_id (foreign key)
├── quantity
├── price
├── beneficiary_number
├── product_variant_id
└── ... other fields

settings table:
├── key
├── value
└── mtn_order_pusher_enabled = '1' (← NEW setting)
```

## Request/Response Examples

### Order Push Request
```
POST https://agent.jaybartservices.com/api/v1/buy-other-package
Headers:
  x-api-key: 534c0aab68d0e52e28e06e2d452de15846de3723
  Content-Type: application/json
  Accept: application/json
Body:
{
  "recipient_msisdn": "0249196792",
  "network_id": 3,
  "shared_bundle": 1000
}
```

### Order Push Response (Success)
```json
{
  "success": true,
  "transaction_code": "TXN123456789"
}
```

### Status Check Request
```
POST https://agent.jaybartservices.com/api/v1/order/bulk/status
Headers:
  X-API-KEY: 534c0aab68d0e52e28e06e2d452de15846de3723
  Content-Type: application/json
Body:
{
  "orderid": "TXN123456789",
  "data_size": 1
}
```

### Status Check Response (Success)
```json
{
  "success": true,
  "recored": [
    {
      "id": 182941,
      "orderbatchid": "1626",
      "status": "Processing",
      "phone": "0249196792",
      "isprocessing": 1,
      "deliverydate": "2026-05-19 22:58:16",
      "deliverytdetails": "{...}"
    }
  ]
}
```

## Integration Checklist

```
SETUP
  ☐ Read README_MTN_ORDER_PUSHER.md
  ☐ Review this visual guide
  ☐ Read FIND_CHECKOUT_HANDLER.md

IMPLEMENTATION
  ☐ Locate checkout controller (OrdersController.php)
  ☐ Find order creation point
  ☐ Add imports (MtnOrderPusherService, Setting)
  ☐ Add pusher call after order creation
  ☐ Add try-catch for error handling
  ☐ Run migration: php artisan migrate
  ☐ Clear cache: php artisan config:cache

SCHEDULER/QUEUE
  ☐ Decide: Scheduler or Queue?
  ☐ If Scheduler: Update app/Console/Kernel.php
  ☐ If Queue: Create job, configure QUEUE_CONNECTION
  ☐ Test: Verify sync runs

TESTING
  ☐ Test order creation
  ☐ Verify reference_id saved
  ☐ Check logs for errors
  ☐ Test admin toggle
  ☐ Test status sync
  ☐ Test SMS notification

DEPLOYMENT
  ☐ Deploy to staging
  ☐ Final testing
  ☐ Deploy to production
  ☐ Monitor logs
  ☐ Document for team
```

## Key Points to Remember

```
┌─────────────────────────────────────────────────────────────┐
│ MTN ORDER PUSHER - KEY POINTS                               │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│ ✓ ONLY handles MTN products (excludes MTN Express)         │
│ ✓ Pushes immediately when order created                    │
│ ✓ Stores transaction code as reference_id                  │
│ ✓ Can enable/disable via admin toggle                      │
│ ✓ Syncs status automatically every 5 minutes               │
│ ✓ Sends SMS when order completes                           │
│ ✓ Safe: Errors won't fail checkout                         │
│ ✓ Logged: All operations logged                            │
│ ✓ Formatted: Phone numbers auto-formatted                  │
│                                                               │
│ ⚠ Still need: Add pusher call to checkout                  │
│ ⚠ Still need: Set up scheduler/queue                       │
│ ⚠ Still need: Run migration                                │
│ ⚠ Still need: Test everything                              │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## Quick Commands Reference

```bash
# Run migration
php artisan migrate

# Clear cache
php artisan config:cache

# Check setting in database
php artisan tinker
>>> App\Models\Setting::get('mtn_order_pusher_enabled')

# Test order push
>>> $order = App\Models\Order::latest()->first()
>>> (new App\Services\MtnOrderPusherService())->pushOrderToApi($order)
>>> $order->refresh(); $order->reference_id;

# Test status sync
>>> app(App\Services\OrderStatusSyncService::class)->syncOrderStatuses()

# View logs
tail -f storage/logs/laravel.log | grep -i mtn

# Check routes
php artisan route:list | grep toggle-mtn

# Run queue listener (if using queue)
php artisan queue:listen
```

## Status at a Glance

| Component | Status | Where |
|-----------|--------|-------|
| Services | ✓ Done | app/Services/ |
| Controller | ✓ Done | AdminDashboardController.php |
| Routes | ✓ Done | routes/web.php |
| Frontend | ✓ Done | Dashboard.tsx |
| Migration | ✓ Done | database/migrations/ |
| Docs | ✓ Done | .md files |
| Checkout Integration | ⚠ TODO | Your checkout handler |
| Scheduler Setup | ⚠ TODO | app/Console/Kernel.php |
| Testing | ⚠ TODO | Manual testing |

---

**Ready to integrate? Start here:** FIND_CHECKOUT_HANDLER.md
