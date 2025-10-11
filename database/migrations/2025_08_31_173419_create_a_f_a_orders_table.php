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
        Schema::create('afa_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('afa_product_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['PENDING', 'COMPLETED', 'CANCELLED']);
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->date('dob');
            $table->string('occupation');
            $table->string('region');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afa_orders');
    }
};
