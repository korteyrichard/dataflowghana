<?php
// Migration: Update products table for variants
// File: database/migrations/xxxx_xx_xx_update_products_table_for_variants.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add has_variants field if not already present
            if (!Schema::hasColumn('products', 'has_variants')) {
                $table->boolean('has_variants')->default(false)->after('product_type');
            }

            // ✅ Do NOT drop columns that never existed in products
            // (price, quantity, status were never in your products table)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'has_variants')) {
                $table->dropColumn('has_variants');
            }

            // No need to restore price/quantity/status since they were never in products
        });
    }
};
