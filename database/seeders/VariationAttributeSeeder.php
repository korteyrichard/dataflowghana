<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VariationAttribute;

class VariationAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            [
                'name' => 'Size',
                'values' => [
                    '0.5GB', '1GB', '2GB', '3GB', '4GB', '5GB', '6GB', '7GB', '8GB', '9GB', '10GB',
                    '15GB', '20GB', '25GB', '30GB', '35GB', '40GB', '45GB', '50GB', '60GB', '70GB',
                    '80GB', '90GB', '100GB', '120GB', '150GB', '200GB', '250GB', '300GB', '350GB',
                    '400GB', '450GB', '500GB'
                ]
            ],
            [
                'name' => 'Duration',
                'values' => ['1 Day', '7 Days', '30 Days', '90 Days', 'Non-Expiry']
            ],
            [
                'name' => 'Type',
                'values' => ['Regular', 'Night', 'Weekend', 'Social Media', 'Video Streaming']
            ]
        ];

        foreach ($attributes as $attribute) {
            VariationAttribute::create($attribute);
        }
    }
}