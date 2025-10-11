<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '0500123456',
            'business_name' => 'Test Business',
            'password' => Hash::make('password'),
            'wallet_balance' => 1000.00,
            'role' => 'customer'
        ]);

        User::create([
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'phone' => '0501234567',
            'business_name' => 'Agent Business',
            'password' => Hash::make('password'),
            'wallet_balance' => 5000.00,
            'role' => 'agent'
        ]);

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'phone' => '0502345678',
            'business_name' => 'Admin Corp',
            'password' => Hash::make('password'),
            'wallet_balance' => 10000.00,
            'role' => 'admin'
        ]);
    }
}