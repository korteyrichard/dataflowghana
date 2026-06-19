<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existingIndexes = $this->getExistingIndexes('carts');
        
        Schema::table('carts', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('idx_carts_user_id', $existingIndexes)) {
                $table->index('user_id', 'idx_carts_user_id');
            }
        });

        $existingOrderIndexes = $this->getExistingIndexes('orders');
        
        Schema::table('orders', function (Blueprint $table) use ($existingOrderIndexes) {
            if (!in_array('idx_orders_user_id', $existingOrderIndexes)) {
                $table->index('user_id', 'idx_orders_user_id');
            }
            if (!in_array('idx_orders_created_at', $existingOrderIndexes)) {
                $table->index('created_at', 'idx_orders_created_at');
            }
            if (!in_array('idx_orders_user_status', $existingOrderIndexes)) {
                $table->index(['user_id', 'status'], 'idx_orders_user_status');
            }
        });

        $existingTransactionIndexes = $this->getExistingIndexes('transactions');
        
        Schema::table('transactions', function (Blueprint $table) use ($existingTransactionIndexes) {
            if (!in_array('idx_transactions_user_id', $existingTransactionIndexes)) {
                $table->index('user_id', 'idx_transactions_user_id');
            }
            if (!in_array('idx_transactions_status_type', $existingTransactionIndexes)) {
                $table->index(['status', 'type'], 'idx_transactions_status_type');
            }
        });

        $existingUserIndexes = $this->getExistingIndexes('users');
        
        Schema::table('users', function (Blueprint $table) use ($existingUserIndexes) {
            if (!in_array('idx_users_role', $existingUserIndexes)) {
                $table->index('role', 'idx_users_role');
            }
        });
    }

    public function down(): void
    {
        $existingIndexes = $this->getExistingIndexes('carts');
        
        Schema::table('carts', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('idx_carts_user_id', $existingIndexes)) {
                $table->dropIndex('idx_carts_user_id');
            }
        });

        $existingOrderIndexes = $this->getExistingIndexes('orders');
        
        Schema::table('orders', function (Blueprint $table) use ($existingOrderIndexes) {
            if (in_array('idx_orders_user_id', $existingOrderIndexes)) {
                $table->dropIndex('idx_orders_user_id');
            }
            if (in_array('idx_orders_created_at', $existingOrderIndexes)) {
                $table->dropIndex('idx_orders_created_at');
            }
            if (in_array('idx_orders_user_status', $existingOrderIndexes)) {
                $table->dropIndex('idx_orders_user_status');
            }
        });

        $existingTransactionIndexes = $this->getExistingIndexes('transactions');
        
        Schema::table('transactions', function (Blueprint $table) use ($existingTransactionIndexes) {
            if (in_array('idx_transactions_user_id', $existingTransactionIndexes)) {
                $table->dropIndex('idx_transactions_user_id');
            }
            if (in_array('idx_transactions_status_type', $existingTransactionIndexes)) {
                $table->dropIndex('idx_transactions_status_type');
            }
        });

        $existingUserIndexes = $this->getExistingIndexes('users');
        
        Schema::table('users', function (Blueprint $table) use ($existingUserIndexes) {
            if (in_array('idx_users_role', $existingUserIndexes)) {
                $table->dropIndex('idx_users_role');
            }
        });
    }

    private function getExistingIndexes($table): array
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}`");
        return array_unique(array_map(fn($index) => $index->Key_name, $indexes));
    }
};
