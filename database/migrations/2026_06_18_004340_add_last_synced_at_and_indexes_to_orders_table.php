<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            // ✅ Add column only if it does not exist
            if (! Schema::hasColumn('orders', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('updated_at');
            }

        });

        // ✅ Add indexes safely — check existence first to prevent duplicate key errors
        $existingIndexes = $this->getExistingIndexes();

        Schema::table('orders', function (Blueprint $table) use ($existingIndexes) {

            if (! in_array('idx_orders_status', $existingIndexes)) {
                $table->index('status', 'idx_orders_status');
            }

            if (! in_array('idx_orders_reference', $existingIndexes)) {
                $table->index('reference_id', 'idx_orders_reference');
            }

            if (! in_array('idx_orders_last_synced', $existingIndexes)) {
                $table->index('last_synced_at', 'idx_orders_last_synced');
            }

        });
    }

    public function down(): void
    {
        $existingIndexes = $this->getExistingIndexes();

        Schema::table('orders', function (Blueprint $table) use ($existingIndexes) {

            if (in_array('idx_orders_status', $existingIndexes)) {
                $table->dropIndex('idx_orders_status');
            }

            if (in_array('idx_orders_reference', $existingIndexes)) {
                $table->dropIndex('idx_orders_reference');
            }

            if (in_array('idx_orders_last_synced', $existingIndexes)) {
                $table->dropIndex('idx_orders_last_synced');
            }

            if (Schema::hasColumn('orders', 'last_synced_at')) {
                $table->dropColumn('last_synced_at');
            }

        });
    }

    /**
     * Retrieve all existing index names on the orders table.
     */
    private function getExistingIndexes(): array
    {
        $indexes = DB::select("SHOW INDEX FROM `orders`");

        return array_unique(
            array_map(fn($index) => $index->Key_name, $indexes)
        );
    }
};