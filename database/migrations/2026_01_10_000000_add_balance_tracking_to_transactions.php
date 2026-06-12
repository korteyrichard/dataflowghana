<?php

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
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('balance_before', 15, 2)->nullable()->after('amount');
            $table->decimal('balance_after', 15, 2)->nullable()->after('balance_before');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_api_order')->default(false)->after('api_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('balance_before');
            $table->dropColumn('balance_after');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_api_order');
        });
    }
};
