<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afa_orders', function (Blueprint $table) {
            if (Schema::hasColumn('afa_orders', 'ghana_card')) {
                $table->dropColumn('ghana_card');
            }
        });
    }

    public function down(): void
    {
        Schema::table('afa_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('afa_orders', 'ghana_card')) {
                $table->string('ghana_card')->nullable();
            }
        });
    }
};
