# DataMaster API V2 Migration Summary

## Overview
Updated DataMaster API integration from V1 to V2. The new version simplifies the API by removing the need for package IDs and automatically detecting networks from phone number prefixes.

## Changes Made

### 1. Configuration Update (`config/services.php`)
- **Base URL**: Changed from `https://user.datamastagh.shop/developer/api/v1` to `https://user.datamastagh.shop/developer/api/v2`
- This is the default fallback URL used when `DATAMASTER_BASE_URL` environment variable is not set

### 2. Order Placement Service (`app/Services/MtnExpressOrderPusherService.php`)

#### Request Format Changes
**V1 (Old)**:
```php
$payload = [
    'package_id' => $packageId,
    'customer_phone' => $beneficiaryPhone
];
```

**V2 (New)**:
```php
$payload = [
    'beneficiary_number' => $beneficiaryPhone,
    'data_size' => $dataSize  // in GB (e.g., 1, 1.5, 2, 5)
];
```

#### Method Changes
- Removed `getPackageIdFromVariant()` method that mapped sizes to package IDs
- Added `getDataSizeFromVariant()` method that extracts numeric data size from variant attributes
- The new method converts size strings (e.g., "1GB", "1.5GB") to float values (1, 1.5)

#### Benefits
- No need to maintain package ID mappings
- API automatically detects network from phone prefix (024=MTN, 020=Vodafone, etc.)
- Simpler, more maintainable code

### 3. Order Status Sync Service (`app/Services/DataMasterOrderStatusSyncService.php`)

#### Response Format Changes
**V1 (Old)**:
```php
$deliveryStatus = $orderData['delivery_status'] ?? '';
$paymentStatus = $orderData['payment_status'] ?? '';
```

**V2 (New)**:
```php
$paymentStatus = $orderData['status']['payment'] ?? '';
$deliveryStatus = $orderData['status']['delivery'] ?? '';
```

The V2 API nests payment and delivery status under a `status` object.

## Environment Variables
No changes needed to `.env` file. The existing credentials remain valid:
- `DATAMASTER_BASE_URL` - Now defaults to V2 endpoint
- `DATAMASTER_SECRET_KEY` - Same as before
- `DATAMASTER_PUBLIC_KEY` - Same as before

## Testing Recommendations

1. **Test Order Placement**:
   - Place an MTN order with various data sizes (1GB, 1.5GB, 2GB, etc.)
   - Verify the order is created successfully
   - Check that `reference_id` is populated with the order number

2. **Test Status Sync**:
   - Run the status sync command: `php artisan datamaster:sync-orders`
   - Verify orders are updated with correct status
   - Check SMS notifications are sent when orders complete

3. **Verify Phone Number Formatting**:
   - Test with different phone formats (0244123456, 244123456, etc.)
   - Ensure all are correctly formatted to 10-digit format starting with 0

## API Documentation Reference
- Base URL: `https://user.datamastagh.shop/developer/api/v2/`
- Endpoints used:
  - `POST /orders/place` - Place single order
  - `GET /orders/status` - Check order status
  - `GET /wallet` - Check wallet balance (if needed)

## Rollback Instructions
If issues occur, revert to V1 by:
1. Changing `DATAMASTER_BASE_URL` in `.env` to `https://user.datamastagh.shop/developer/api/v1`
2. Reverting the service files to their previous versions
