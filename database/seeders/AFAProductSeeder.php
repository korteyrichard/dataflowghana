<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AFAProduct;

class AFAProductSeeder extends Seeder
{
    public function run(): void
    {
        AFAProduct::create([
            'name' => 'AFA Basic Package',
            'status' => 'IN_STOCK',
            'price' => 50.00
        ]);

        AFAProduct::create([
            'name' => 'AFA Premium Package',
            'status' => 'IN_STOCK',
            'price' => 100.00
        ]);

        AFAProduct::create([
            'name' => 'AFA Enterprise Package',
            'status' => 'IN_STOCK',
            'price' => 200.00
        ]);

        AFAProduct::create([
            'name' => 'AFA Special Offer',
            'status' => 'OUT_OF_STOCK',
            'price' => 75.00
        ]);
    }
}