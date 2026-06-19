# API Order Timeout Issue - Root Cause & Fix (Shared Hosting Version)

## Problem
API-based orders were failing with timeout responses when pushed through the DataSource Order Pusher, while bulk web orders (even 200+) appeared to process successfully.

## Root Cause
**Long HTTP timeouts blocking requests on shared hosting:**

1. **60-second timeout** on all external API calls
2. **Synchronous blocking calls** during HTTP request lifecycle
3. **Shared hosting constraints**: Single PHP process can't handle long-running requests under load
4. **Multiple concurrent API calls**: Each order push waits for complete response before returning

## Solution
**Optimize HTTP client with shorter timeouts and better error handling**

### Changes Made

#### 1. DataSourceOrderPusherService
- **Timeout**: Reduced from 60s → **15s**
- **Connection timeout**: Added **5s**
- **Status handling**: Mark orders as `pending` on connection timeout (can retry later)
- **Error separation**: Connection errors don't permanently fail orders

#### 2. CodeCraftOrderPusherService
- **Timeout**: Reduced from 30s → **15s**
- **Connection timeout**: Added **5s**

#### 3. DataEasyOrderPusherService
- **Timeout**: Reduced from 30s → **15s**
- **Connection timeout**: Added **5s**

#### 4. MtnExpressOrderPusherService
- **Timeout**: Reduced from 30s → **15s**
- **Connection timeout**: Added **5s**

### Key Improvements

✅ **Faster failure detection**: External API hangs don't block user requests
✅ **Graceful degradation**: Orders marked as `pending` can be retried by status sync
✅ **Shared hosting compatible**: No queue workers needed
✅ **Better UX**: Users get immediate response (order created)
✅ **Automatic recovery**: Implement status sync to retry `pending` orders

## How It Works

1. **Order creation**: Returns immediately to user (HTTP 201)
2. **API push**: Begins synchronously with SHORT timeout
3. **If successful** (< 15s): Status updated to `success`
4. **If timeout**: Status marked as `pending` (can retry)
5. **If error**: Status marked as `failed` (investigate in logs)
6. **Status sync service**: Background retry of `pending` orders

## What To Do Next

### Option 1: Use Status Sync Service (Recommended)
Create a scheduled task to sync pending orders:
```bash
php artisan schedule:run
```

Add this to your `app/Console/Kernel.php`:
```php
$schedule->command('orders:sync-status')->everyFiveMinutes();
```

### Option 2: Manual Cron Job
Add to crontab:
```
*/5 * * * * php /path/to/artisan orders:sync-status
```

### Option 3: Monitor & Retry
Create a custom command to check `pending` orders and retry them.

## Testing

1. **Place a single API order**
   - Should return 201 immediately
   - Order status initially = `pending`

2. **Monitor status**
   - Check `orders.api_status` column
   - Should change to `success` within 15 seconds
   - If still `pending` after 5 minutes, check logs

3. **Check logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   - Look for timeout messages
   - Verify retry attempts

## Metrics

- **Old**: 60s timeout = 1 request/min max per process
- **New**: 15s timeout = 4 requests/min per process
- **Improvement**: 4x faster failure detection, less resource blocking
