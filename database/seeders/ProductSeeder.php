<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // MTN Data Bundles
        Product::create([
            'name' => 'MTN',
            'description' => 'MTN mobile data packages',
            'network' => 'MTN',
            'product_type' => 'customer_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

        // Vodafone Data Bundles
        Product::create([
            'name' => 'Telecel',
            'description' => 'Vodafone mobile data packages',
            'network' => 'Telecel',
            'product_type' => 'customer_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

        // AirtelTigo Data Bundles
        Product::create([
            'name' => 'Ishare',
            'description' => 'AirtelTigo mobile data packages',
            'network' => 'ISHARE',
            'product_type' => 'customer_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

            Product::create([
            'name' => 'Bigtime',
            'description' => 'AirtelTigo mobile data packages',
            'network' => 'BIGTIME',
            'product_type' => 'customer_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);


        // MTN Data Bundles
        Product::create([
            'name' => 'MTN',
            'description' => 'MTN mobile data packages',
            'network' => 'MTN',
            'product_type' => 'agent_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

        // Vodafone Data Bundles
        Product::create([
            'name' => 'Telecel',
            'description' => 'Telecel mobile data packages',
            'network' => 'TELECEL',
            'product_type' => 'agent_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

        // AirtelTigo Data Bundles
        Product::create([
            'name' => 'Ishare',
            'description' => 'AT Ishare mobile  data packages',
            'network' => 'ISHARE',
            'product_type' => 'agent_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

            Product::create([
            'name' => 'Bigtime',
            'description' => 'AT Bigtime mobile data packages',
            'network' => 'BIGTIME',
            'product_type' => 'agent_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

        // Dealer Products
        Product::create([
            'name' => 'MTN Dealer',
            'description' => 'MTN dealer mobile data packages',
            'network' => 'MTN',
            'product_type' => 'dealer_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

        Product::create([
            'name' => 'Telecel Dealer',
            'description' => 'Telecel dealer mobile data packages',
            'network' => 'Telecel',
            'product_type' => 'dealer_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

        Product::create([
            'name' => 'Ishare Dealer',
            'description' => 'Ishare dealer mobile data packages',
            'network' => 'Ishare',
            'product_type' => 'dealer_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);

        Product::create([
            'name' => 'Bigtime Dealer',
            'description' => 'Bigtime dealer mobile data packages',
            'network' => 'Bigtime',
            'product_type' => 'dealer_product',
            'expiry' => '30 days',
            'has_variants' => true
        ]);
    }
}