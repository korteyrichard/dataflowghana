# Transaction Balance Tracking & API Order Tagging Implementation

## Overview
This implementation adds balance before/after tracking to all transactions and tags API-created orders for easy identification.

## Changes Made

### 1. Database Migration
**File**: `database/migrations/2026_01_10_000000_add_balance_tracking_to_transactions.php`
- Added `balance_before` (nullable decimal) to transactions table
- Added `balance_after` (nullable decimal) to transactions table
- Added `is_api_order` (boolean, default false) to orders table

### 2. Models
**Transaction Model** (`app/Models/Transaction.php`):
- Added `balance_before` and `balance_after` to fillable attributes

**Order Model** (`app/Models/Order.php`):
- Added `is_api_order` to fillable attributes

### 3. Controllers

#### OrdersController (`app/Http/Controllers/OrdersController.php`)
- Updated `checkout()` method to:
  - Track balance before and after for each order transaction
  - Calculate balance progression as items are deducted
  - Store balance_before and balance_after in transaction records

#### Api/OrderController (`app/Http/Controllers/Api/OrderController.php`)
- Updated `store()` method to:
  - Track balance before and after for API orders
  - Create transaction records with balance information
  - Set `is_api_order` flag to true for API-created orders
  - Include balance info in transaction record

#### AdminDashboardController (`app/Http/Controllers/AdminDashboardController.php`)
- Updated `creditWallet()` method to track balance before/after
- Updated `debitWallet()` method to track balance before/after
- Updated `updateOrderStatus()` method to track balance before/after for refunds
- Updated `bulkUpdateOrderStatus()` method to track balance before/after for refunds

#### TransactionsController (`app/Http/Controllers/TransactionsController.php`)
- Updated `index()` method to eager load order relationship with `with('order')`

### 4. Frontend Pages

#### Dashboard Transactions Page (`resources/js/pages/Dashboard/transactions.tsx`)
- Added `balance_before` and `balance_after` fields to Transaction interface
- Added `is_api_order` flag to Order interface
- Updated table headers to include "Balance Before" and "Balance After" columns
- Updated table rows to display balance information
- Added "API" badge for API-created orders next to transaction type
- Updated mobile view with balance information

#### Admin Transactions Page (`resources/js/pages/Admin/Transactions.tsx`)
- Added `balance_before` and `balance_after` fields to Transaction interface
- Updated Order interface to include `is_api_order` field
- Updated table headers to include "Balance Before" and "Balance After" columns
- Updated table rows to display balance information
- Added "API" badge for API-created orders

## Features Implemented

1. **Balance Tracking**
   - All transactions now show the wallet balance before the transaction
   - All transactions now show the wallet balance after the transaction
   - Works for: regular orders, API orders, admin credits, admin debits, refunds

2. **API Order Identification**
   - API orders are marked with `is_api_order = true` in the database
   - API orders display an "API" badge in transaction views
   - Easy to filter and identify orders made via API

3. **User Transaction History**
   - Users can see their balance progression over time
   - Each transaction clearly shows the impact on their wallet

4. **Admin Transaction Monitoring**
   - Admins can track balance changes for all transactions
   - Easy audit trail for financial transactions
   - Clear identification of API vs manual orders

## How to Deploy

1. Run the migration:
```bash
php artisan migrate
```

2. The changes are backward compatible - existing transactions will have NULL for balance_before/balance_after

3. All new transactions will automatically capture balance information

## Balance Calculation Logic

When processing orders in checkout:
- Store the initial balance before deduction
- For each item processed:
  - Calculate balance_before_item = initial_balance - (sum of previously processed items)
  - Deduct the item amount
  - Calculate balance_after_item = balance_before_item - item_amount
  - Store both values in the transaction record
- This creates an accurate transaction history

For API orders:
- Capture balance before decrement
- Decrement wallet
- Capture balance after (using fresh() to get updated value)
- Store in transaction record

For admin operations (credit/debit):
- Capture balance before operation
- Perform the operation
- Capture balance after (using fresh() to get updated value)
- Store in transaction record
