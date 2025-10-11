<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductVariant;

class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();

        foreach ($products as $product) {
            // Common data bundle sizes with prices
            $variants = [
                ['size' => '1gb', 'price' => 5.00],
                ['size' => '2gb', 'price' => 9.00],
                ['size' => '3gb', 'price' => 13.00],
                ['size' => '5gb', 'price' => 20.00],
                ['size' => '10gb', 'price' => 35.00],
                ['size' => '20gb', 'price' => 65.00],
            ];

            foreach ($variants as $variant) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'price' => $variant['price'],
                    'quantity' => 1000,
                    'status' => 'IN STOCK',
                    'variant_attributes' => [
                        'size' => $variant['size']
                    ]
                ]);
            }
        }
    }
}