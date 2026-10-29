<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmation;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $items, string $email = 'ravi@test.com'): array
    {
        return [
            'email' => $email,
            'name' => 'Ravi',
            'items' => $items,
        ];
    }


    public function test_order_is_created_with_correct_totals_and_stock_deducted(): void
    {
        Queue::fake();

        $colgate = Product::factory()->create(['price' => 50, 'tax_percent' => 8, 'stock' => 40]);
        $parle = Product::factory()->create(['price' => 10, 'tax_percent' => 8, 'stock' => 100]);

        $response = $this->postJson('/orders', $this->payload([
            ['product_id' => $colgate->id, 'quantity' => 2],
            ['product_id' => $parle->id, 'quantity' => 5],
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('subtotal', '150.00')
            ->assertJsonPath('tax', '12.00')
            ->assertJsonPath('grand_total', '162.00');

        $this->assertEquals(38, $colgate->fresh()->stock);
        $this->assertEquals(95, $parle->fresh()->stock);
        $this->assertDatabaseHas('customers', ['email' => 'ravi@test.com']);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_order_fails_when_stock_is_insufficient(): void
    {
        Queue::fake();

        $eggs = Product::factory()->create(['stock' => 2]);

        $response = $this->postJson('/orders', $this->payload([
            ['product_id' => $eggs->id, 'quantity' => 5],
        ]));

        $response->assertStatus(422);

        // Stock maaraama irukkanum, order create aagakoodadhu
        $this->assertEquals(2, $eggs->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
        Queue::assertNothingPushed();
    }

    public function test_one_failing_line_rolls_back_the_whole_order(): void
    {
        Queue::fake();

        $milk = Product::factory()->create(['stock' => 10]);
        $eggs = Product::factory()->create(['stock' => 1]);

        $this->postJson('/orders', $this->payload([
            ['product_id' => $milk->id, 'quantity' => 3],
            ['product_id' => $eggs->id, 'quantity' => 5],
        ]))->assertStatus(422);

        // Milk valid-a irundhaalum, rollback aagi stock 10 appadiye irukkanum
        $this->assertEquals(10, $milk->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_same_product_repeated_in_lines_is_summed(): void
    {
        Queue::fake();

        $bread = Product::factory()->create(['stock' => 4]);

        // 3 + 2 = 5 > 4, so fail aaganum
        $this->postJson('/orders', $this->payload([
            ['product_id' => $bread->id, 'quantity' => 3],
            ['product_id' => $bread->id, 'quantity' => 2],
        ]))->assertStatus(422);

        $this->assertEquals(4, $bread->fresh()->stock);
    }

    public function test_unknown_product_is_rejected_by_validation(): void
    {
        $this->postJson('/orders', $this->payload([
            ['product_id' => 9999, 'quantity' => 1],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson('/orders', $this->payload([
            ['product_id' => $product->id, 'quantity' => 0],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_confirmation_job_is_dispatched_on_success(): void
    {
        Queue::fake();

        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson('/orders', $this->payload([
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->assertStatus(201);

        Queue::assertPushed(SendOrderConfirmation::class);
    }

    public function test_existing_customer_is_reused(): void
    {
        Queue::fake();

        Customer::factory()->create(['email' => 'ravi@test.com']);
        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson('/orders', $this->payload([
            ['product_id' => $product->id, 'quantity' => 1],
        ]))->assertStatus(201);

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_order_history_returns_customer_orders(): void
    {
        Queue::fake();

        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson('/orders', $this->payload([
            ['product_id' => $product->id, 'quantity' => 1],
        ]));

        $this->getJson('/customers/ravi@test.com/orders')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_low_stock_endpoint_returns_only_products_below_threshold(): void
    {
        config(['store.low_stock_threshold' => 10]);

        Product::factory()->create(['name' => 'Low One', 'stock' => 3]);
        Product::factory()->create(['name' => 'Plenty', 'stock' => 50]);

        $this->getJson('/products/low-stock')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Low One');
    }
}