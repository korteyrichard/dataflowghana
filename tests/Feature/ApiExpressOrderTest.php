<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiExpressOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_requests_receive_json_instead_of_a_login_page(): void
    {
        $this->post('/api/v1/express-orders')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_it_creates_an_express_order_using_the_express_product_name(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'agent', 'wallet_balance' => 100])->save();
        Sanctum::actingAs($user);

        $expressProduct = Product::create([
            'name' => 'MTN Express',
            'network' => 'MTN',
            'product_type' => 'agent_product',
        ]);
        $regularProduct = Product::create([
            'name' => 'MTN',
            'network' => 'MTN',
            'product_type' => 'agent_product',
        ]);
        $expressVariant = ProductVariant::create([
            'product_id' => $expressProduct->id,
            'price' => 12,
            'quantity' => '1',
            'variant_attributes' => ['size' => '2GB'],
        ]);
        ProductVariant::create([
            'product_id' => $regularProduct->id,
            'price' => 10,
            'quantity' => '1',
            'variant_attributes' => ['size' => '2GB'],
        ]);

        $this->postJson('/api/v1/express-orders', [
            'beneficiary_number' => '0241234567',
            'network' => 'MTN Express',
            'size' => '2GB',
        ])->assertCreated()
            ->assertJsonPath('order.network', 'MTN')
            ->assertJsonPath('order.total', '12.00')
            ->assertJsonPath('order.products.0.name', 'MTN Express');

        $this->assertDatabaseHas('order_product', [
            'product_id' => $expressProduct->id,
            'product_variant_id' => $expressVariant->id,
            'beneficiary_number' => '0241234567',
        ]);

        $this->postJson('/api/v1/normal-orders', [
            'beneficiary_number' => '0247654321',
            'network_id' => 5,
            'size' => '2GB',
        ])->assertCreated()
            ->assertJsonPath('order.products.0.name', 'MTN');

        $this->assertEquals(78, (float) $user->fresh()->wallet_balance);
    }

    public function test_express_endpoint_only_accepts_mtn_network_ids(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/express-orders', [
            'beneficiary_number' => '0241234567',
            'network_id' => 6,
            'size' => '2GB',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('network_id');
    }
}
