<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\User;
use App\Models\ProductVariant;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $variants = ProductVariant::all();

        foreach ($users as $user) {
            // Create 2-3 orders per user
            for ($i = 0; $i < rand(2, 3); $i++) {
                $variant = $variants->random();
                
                $order = Order::create([
                    'user_id' => $user->id,
                    'total' => $variant->price,
                    'status' => ['pending', 'processing', 'completed'][rand(0, 2)],
                    'beneficiary_number' => '050' . rand(1000000, 9999999),
                    'network' => $variant->product->network
                ]);

                // Attach product to order
                $order->products()->attach($variant->product_id, [
                    'quantity' => 1,
                    'price' => $variant->price,
                    'beneficiary_number' => $order->beneficiary_number,
                    'product_variant_id' => $variant->id
                ]);
            }
        }
    }
}