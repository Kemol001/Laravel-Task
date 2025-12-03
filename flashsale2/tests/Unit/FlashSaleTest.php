<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\HoldExpiryJob;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'name' => 'Test Product',
            'price' => 100,
            'stock' => 10,
        ]);
    }

    /** @test */
    public function prevents_overselling_under_parallel_holds()
    {
        $responses = collect(range(1, 15))->map(function ($i) {
            return $this->postJson('/api/holds', [
                'product_id' => $this->product->id,
                'qty' => 1,
            ]);
        });

        $success = $responses->filter(fn($r) => $r->status() === 200);
        $fail = $responses->filter(fn($r) => $r->status() === 422);

        $this->assertCount(10, $success); // stock = 10
        $this->assertCount(5, $fail); // remaining 5 attempts fail
    }

    /** @test */
    public function hold_expiry_returns_stock()
    {
        Queue::fake();

        $holdResponse = $this->postJson('/api/holds', [
            'product_id' => $this->product->id,
            'qty' => 5,
        ]);

        $holdId = $holdResponse->json('hold_id');
        $hold = Hold::find($holdId);

        $this->assertEquals(5, $this->product->getAvailableStock());

        // Dispatch expiry immediately
        HoldExpiryJob::dispatch($hold)->handle();

        $hold->release();
        $this->assertEquals(10, $this->product->fresh()->getAvailableStock());
        $this->assertEquals('expired', $hold->fresh()->status);
    }

    /** @test */
    public function webhook_is_idempotent()
    {
        $hold = Hold::create([
            'id' => Str::uuid(),
            'product_id' => $this->product->id,
            'qty' => 2,
            'expires_at' => now()->addMinutes(2),
        ]);

        $order = Order::create(['hold_id' => $hold->id]);

        $payload = [
            'idempotency_key' => 'abc123',
            'order_id' => $order->id,
            'status' => 'success',
        ];

        $this->postJson('/api/payments/webhook', $payload);
        $this->postJson('/api/payments/webhook', $payload);

        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertDatabaseCount('webhook_logs', 1);
    }

     /** @test */
    public function webhook_before_order_creation_still_works()
    {
        $hold = Hold::create([
            'id' => Str::uuid(),
            'product_id' => $this->product->id,
            'qty' => 2,
            'expires_at' => now()->addMinutes(2),
        ]);

        $orderId = Str::uuid(); // deterministic UUID for webhook & later order

        $payload = [
            'idempotency_key' => 'webhook-first',
            'order_id' => $orderId,
            'status' => 'success',
        ];

        // Webhook arrives before the order exists
        $response = $this->postJson('/api/payments/webhook', $payload);
        $response->assertStatus(200);

        // Create the order later using the same UUID
        $order = Order::create([
            'id' => $orderId,
            'hold_id' => $hold->id,
        ]);
        // Assert that the webhook was correctly logged
        $this->assertDatabaseHas('webhook_logs', [
            'idempotency_key' => 'webhook-first',
            'order_id' => $order->id,
        ]);

        // Order should be marked as paid
        $this->assertEquals('paid', $order->fresh()->status);
    }

    /** @test */
    public function cannot_create_order_from_expired_hold()
    {
        $hold = Hold::create([
            'id' => Str::uuid(),
            'product_id' => $this->product->id,
            'qty' => 2,
            'expires_at' => now()->subMinute(), // already expired
        ]);

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_create_two_orders_from_same_hold()
    {
        $hold = Hold::create([
            'id' => Str::uuid(),
            'product_id' => $this->product->id,
            'qty' => 2,
            'expires_at' => now()->addMinutes(2),
        ]);

        Order::create(['hold_id' => $hold->id]);

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id,
        ]);

        $response->assertStatus(422);
    }

}