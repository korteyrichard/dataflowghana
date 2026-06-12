# MTN Order Pusher Service - Implementation Guide

## Overview
This implementation adds a dedicated MTN order pusher service for handling MTN-specific orders using the provided Order Pusher API endpoints. Includes status sync, admin toggles, and full integration with existing order processing.

## Files Created

### 1. MtnOrderPusherService.php
**Location:** `app/Services/MtnOrderPusherService.php`

Responsible for pushing MTN orders to the Order Pusher API endpoint.

**Key Features:**
- Pushes orders to `https://agent.jaybartservices.com/api/v1/buy-other-package`
- Extracts data size from product variants
- Formats phone numbers correctly (10-digit format)
- Saves transaction code as reference_id on success
- Sets api_status to 'success' or 'failed'
- Comprehensive logging for debugging

**Usage:**
```php
$service = new MtnOrderPusherService();
$service->pushOrderToApi($order);
```

### 2. MtnOrderStatusSyncService.php
**Location:** `app/Services/MtnOrderStatusSyncService.php`

Syncs MTN order statuses from the Order Pusher API using the bulk status endpoint.

**Key Features:**
- Uses bulk status endpoint: `/api/v1/order/bulk/status`
- Maps external statuses to internal statuses (successful → completed, etc.)
- Sends SMS notifications on completion
- Handles MTN-specific orders (excluding MTN Express)
- Logs all sync attempts and status changes

**Endpoint Details:**
```
POST /api/v1/order/bulk/status
Headers:
  X-API-KEY: {api_key}
  Content-Type: application/json
Body:
  orderid: {reference_id}
  data_size: 1
Response:
  success: true/false
  recored: [{status: "...", ...}]
```

**Usage:**
```php
$service = new MtnOrderStatusSyncService($smsService);
$service->syncOrderStatuses();
```

### 3. Migration File
**Location:** `database/migrations/2026_01_10_000001_add_mtn_order_pusher_setting.php`

Creates the `mtn_order_pusher_enabled` setting in the database.

**Run Migration:**
```bash
php artisan migrate
```

## Files Modified

### 1. OrderStatusSyncService.php
**Changes:**
- Added import for `MtnOrderStatusSyncService`
- Instantiated and called `MtnOrderStatusSyncService::syncOrderStatuses()` in main sync
- Updated order filtering to skip MTN orders (now handled by dedicated service)
- Removed old `syncMtnOrderStatus()` method (now in dedicated service)
- Removed old `mapMtnStatus()` helper method (now in dedicated service)

**Status Handling:**
- MTN Express → Handled by DataMaster sync
- MTN (non-Express) → Handled by MTN Order Pusher sync
- Telecel/Ishare/Bigtime → Handled by CodeCraft sync
- Others → Not synced

### 2. AdminDashboardController.php
**Changes:**
- Added `mtnOrderPusherEnabled` to dashboard Inertia render
- Created new `toggleMtnOrderPusher()` method
- Reads/writes `mtn_order_pusher_enabled` setting

### 3. routes/web.php
**Changes:**
- Added route: `POST /admin/toggle-mtn-order-pusher`
- Maps to `AdminDashboardController@toggleMtnOrderPusher`
- Route name: `admin.toggle.mtn.order.pusher`

### 4. Admin Dashboard UI (Dashboard.tsx)
**Changes:**
- Added `mtnOrderPusherEnabled` to component props
- Created `toggleMtnOrderPusher()` function
- Added MTN Order Pusher toggle card in System Controls section
- Shows appropriate status message based on enabled state

## Environment Configuration

Your `.env` file already has:
```
ORDER_PUSHER_BASE_URL=https://agent.jaybartservices.com/api/v1
ORDER_PUSHER_API_KEY=534c0aab68d0e52e28e06e2d452de15846de3723
```

The service reads these via Laravel config:
```php
config('services.order_pusher.base_url')
config('services.order_pusher.api_key')
```

## Status Mapping

MTN orders are mapped from external statuses to internal statuses:

| External Status | Internal Status |
|-----------------|-----------------|
| successful      | completed       |
| completed       | completed       |
| delivered       | completed       |
| processing      | processing      |
| pending         | processing      |
| pending2        | processing      |
| failed          | cancelled       |
| cancelled       | cancelled       |

## Integration with Order Processing

The MTN order pusher integrates at two points:

### 1. Order Push (When Order is Created)
In your order checkout flow, if the order contains MTN products and the toggle is enabled:
```php
if (Setting::get('mtn_order_pusher_enabled')) {
    $service = new MtnOrderPusherService();
    $service->pushOrderToApi($order);
}
```

### 2. Status Sync (Periodic, via queue or command)
Called by your existing order status sync job:
```php
// In OrderStatusSyncService
$mtnSync = new MtnOrderStatusSyncService($this->smsService);
$mtnSync->syncOrderStatuses();
```

## Setup Instructions

### Step 1: Run Migration
```bash
php artisan migrate
```

This creates the setting with default value of '1' (enabled).

### Step 2: Verify Environment Variables
Check `.env` file contains:
```
ORDER_PUSHER_BASE_URL=https://agent.jaybartservices.com/api/v1
ORDER_PUSHER_API_KEY=534c0aab68d0e52e28e06e2d452de15846de3723
```

### Step 3: Clear Cache
```bash
php artisan config:cache
```

### Step 4: Verify Admin Dashboard
- Navigate to Admin Dashboard
- Verify MTN Order Pusher toggle appears in System Controls
- Toggle should be enabled by default

### Step 5: Test Order Push
1. Create an MTN product (if not exists)
2. Place an order with MTN product
3. Check order's `reference_id` and `api_status` fields
4. Check logs: `storage/logs/laravel.log`

### Step 6: Test Status Sync
1. Run the status sync command or trigger via queue
2. Verify MTN orders are checked against API
3. Check order status updates
4. Verify SMS notifications sent on completion

## Phone Number Formatting

The service handles phone number formatting:
- 10-digit starting with 0: Used as-is (e.g., "0249196792")
- 9-digit: Prepends 0 (e.g., "249196792" → "0249196792")
- Other formats: Passed through after removing non-digits

## Logging

All operations are logged to `storage/logs/laravel.log`:

**Order Push Logs:**
```
Order pushed to MTN Order Pusher successfully
Failed to sync MTN order status
MTN Order Pusher API exception
```

**Status Sync Logs:**
```
MTN order status updated
MTN Order Pusher API response invalid
MTN status sync exception
```

## API Response Examples

### Successful Order Push Response
```json
{
  "success": true,
  "transaction_code": "TXN123456789"
}
```

### Bulk Status Query Response
```json
{
  "success": true,
  "recored": [
    {
      "id": 182941,
      "orderbatchid": "1626",
      "status": "Processing",
      "phone": "0249196792",
      "isprocessing": 1
    }
  ]
}
```

## Troubleshooting

### Order Not Pushing
1. Verify `mtn_order_pusher_enabled` is set to '1' in settings
2. Check order contains MTN product (case-insensitive)
3. Verify beneficiary_number exists for product
4. Check product has variant with size attribute
5. Review logs for API errors

### Status Not Syncing
1. Verify order has `reference_id` set from push
2. Verify order status is 'pending' or 'processing'
3. Check API credentials in `.env`
4. Review logs for API response errors
5. Verify Moolre SMS service is configured for notifications

### Phone Number Issues
1. Ensure beneficiary_number is 9-10 digits
2. Check no special characters in phone
3. Verify product variant has correct size format

## Disabling MTN Order Pusher

To disable without removing code:
1. Go to Admin Dashboard
2. Toggle "MTN Order Pusher" to OFF
3. Or run: `Setting::set('mtn_order_pusher_enabled', '0')`

Orders will no longer be pushed to API, but status sync will also skip them.

## Future Enhancements

- Add retry logic for failed pushes
- Implement webhook for real-time status updates
- Add order history tracking for each push
- Implement batch order pushing
- Add performance metrics/dashboard
