<?php

namespace Tests\Feature;

use App\Models\BasketItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->buyer()->create();
        $this->product = $this->makeProduct();
        $this->addFarmerStock($this->product, 3, 100);
        Sanctum::actingAs($this->buyer);
    }

    private function placeOrder(int $kg = 20): int
    {
        return $this->postJson('/api/orders', $this->orderPayload([
            ['product_id' => $this->product->id, 'quantity' => $kg, 'quality' => 3],
        ]))->assertCreated()->json('order.id');
    }

    public function test_cancelling_inside_the_window_restores_stock_and_basket(): void
    {
        $orderId = $this->placeOrder(20);
        $this->getJson("/api/products/{$this->product->id}")->assertJsonPath('product.good_quantity', 80);

        // The buyer adds more of the same line before cancelling: the cancelled items merge into it
        $this->postJson('/api/basket/items', ['product_id' => $this->product->id, 'quality' => 3, 'quantity' => 3]);

        $this->travel(10)->seconds();

        $this->postJson("/api/orders/{$orderId}/cancel", ['reason' => 'Changed my mind', 'cancel_reason' => 'Changed my mind'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.cancel_reason', 'Changed my mind')
            ->assertJsonPath('order.confirmed_at', null);

        $order = Order::find($orderId);
        $this->assertNotNull($order->cancelled_at);

        $this->getJson("/api/products/{$this->product->id}")->assertJsonPath('product.good_quantity', 100);

        $this->assertSame(1, BasketItem::where('buyer_id', $this->buyer->id)->count());
        $this->assertDatabaseHas('basket_items', [
            'buyer_id' => $this->buyer->id, 'product_id' => $this->product->id, 'quality' => 3, 'quantity' => 23,
        ]);
    }

    public function test_cancelling_after_the_window_returns_409_and_confirms_the_order(): void
    {
        $orderId = $this->placeOrder();

        $this->travel(21)->seconds();

        $this->postJson("/api/orders/{$orderId}/cancel", ['reason' => null, 'cancel_reason' => null])
            ->assertStatus(409)
            ->assertExactJson([
                'code'    => 'cancel_window_closed',
                'message' => 'This order can no longer be cancelled.',
            ]);

        $order = Order::find($orderId);
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertDatabaseCount('basket_items', 0);
    }

    public function test_cancelling_an_already_cancelled_order_changes_nothing(): void
    {
        $orderId = $this->placeOrder();
        $this->postJson("/api/orders/{$orderId}/cancel", ['reason' => 'first'])->assertOk();
        $this->assertDatabaseHas('basket_items', ['quantity' => 20]);

        $this->travel(60)->seconds();

        $this->postJson("/api/orders/{$orderId}/cancel", ['reason' => 'second'])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.cancel_reason', 'first');

        // The items weren't added to the basket a second time
        $this->assertDatabaseHas('basket_items', ['quantity' => 20]);
    }

    public function test_orders_without_a_cancel_window_cannot_be_cancelled(): void
    {
        $order = Order::create([
            'buyer_id' => $this->buyer->id, 'total_amount' => 100, 'status' => 'pending', 'address' => 'A', 'city' => 'Erbil',
        ]);

        $this->getJson("/api/orders/{$order->id}")->assertJsonPath('order.cancel_window_seconds', 0);

        $this->postJson("/api/orders/{$order->id}/cancel", [])
            ->assertStatus(409)
            ->assertJsonPath('code', 'cancel_window_closed');
    }

    public function test_only_the_orders_buyer_can_cancel(): void
    {
        $orderId = $this->placeOrder();

        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->postJson("/api/orders/{$orderId}/cancel", [])->assertNotFound();
        $this->assertSame('awaiting_confirmation', Order::find($orderId)->status);
    }
}
