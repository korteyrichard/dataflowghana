<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_credit_user_wallet_and_create_transaction()
    {
        // Create admin user
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Create regular user
        $user = User::factory()->create([
            'role' => 'customer',
            'wallet_balance' => 100.00
        ]);

        // Act as admin
        $this->actingAs($admin);

        // Credit user wallet
        $response = $this->post(route('admin.users.credit', $user), [
            'amount' => 50.00
        ]);

        // Assert wallet was credited
        $user->refresh();
        $this->assertEquals(150.00, $user->wallet_balance);

        // Assert transaction was created
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'amount' => 50.00,
            'type' => 'admin_credit',
            'status' => 'completed'
        ]);

        $response->assertRedirect(route('admin.users'));
    }

    public function test_admin_can_debit_user_wallet_and_create_transaction()
    {
        // Create admin user
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Create regular user with sufficient balance
        $user = User::factory()->create([
            'role' => 'customer',
            'wallet_balance' => 100.00
        ]);

        // Act as admin
        $this->actingAs($admin);

        // Debit user wallet
        $response = $this->post(route('admin.users.debit', $user), [
            'amount' => 30.00
        ]);

        // Assert wallet was debited
        $user->refresh();
        $this->assertEquals(70.00, $user->wallet_balance);

        // Assert transaction was created
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'amount' => 30.00,
            'type' => 'admin_debit',
            'status' => 'completed'
        ]);

        $response->assertRedirect(route('admin.users'));
    }

    public function test_admin_cannot_debit_more_than_user_balance()
    {
        // Create admin user
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Create regular user with low balance
        $user = User::factory()->create([
            'role' => 'customer',
            'wallet_balance' => 10.00
        ]);

        // Act as admin
        $this->actingAs($admin);

        // Try to debit more than available balance
        $response = $this->post(route('admin.users.debit', $user), [
            'amount' => 50.00
        ]);

        // Assert wallet was not debited
        $user->refresh();
        $this->assertEquals(10.00, $user->wallet_balance);

        // Assert no transaction was created
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'type' => 'admin_debit'
        ]);

        $response->assertRedirect(route('admin.users'));
    }
}