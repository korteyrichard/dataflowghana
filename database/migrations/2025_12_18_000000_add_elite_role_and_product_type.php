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
        // Alter users table to add 'elite' role to the enum
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'agent', 'admin', 'dealer', 'elite') DEFAULT 'customer'");
        
        // Alter products table to add 'elite_product' to the enum
        DB::statement("ALTER TABLE products MODIFY COLUMN product_type ENUM('agent_product', 'customer_product', 'dealer_product', 'elite_product')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert users table to original enum (will fail if elite role exists - that's intentional for safety)
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'agent', 'admin', 'dealer') DEFAULT 'customer'");
        
        // Revert products table to original enum (will fail if elite_product exists - that's intentional for safety)
        DB::statement("ALTER TABLE products MODIFY COLUMN product_type ENUM('agent_product', 'customer_product', 'dealer_product')");
    }
};
