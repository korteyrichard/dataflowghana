<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afa_orders', function (Blueprint $table) {
            // Check if email column exists, if so rename it
            if (Schema::hasColumn('afa_orders', 'email')) {
                $table->renameColumn('email', 'ghana_card_number');
            } elseif (!Schema::hasColumn('afa_orders', 'ghana_card_number')) {
                // If neither exists, add ghana_card_number
                $table->string('ghana_card_number')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('afa_orders', function (Blueprint $table) {
            if (Schema::hasColumn('afa_orders', 'ghana_card_number')) {
                $table->renameColumn('ghana_card_number', 'email');
            }
        });
    }
};
