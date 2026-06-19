<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alter users table to add 'superAgent' role to the enum
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'agent', 'admin', 'dealer', 'elite', 'superAgent') DEFAULT 'customer'");
        
        // Alter products table to add 'super_agent_product' to the enum
        DB::statement("ALTER TABLE products MODIFY COLUMN product_type ENUM('agent_product', 'customer_product', 'dealer_product', 'elite_product', 'super_agent_product')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert users table to previous enum
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'agent', 'admin', 'dealer', 'elite') DEFAULT 'customer'");
        
        // Revert products table to previous enum
        DB::statement("ALTER TABLE products MODIFY COLUMN product_type ENUM('agent_product', 'customer_product', 'dealer_product', 'elite_product')");
    }
};
