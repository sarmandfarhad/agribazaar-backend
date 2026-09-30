<?php

namespace Tests\Feature;

use App\Models\BasketItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_uses_server_prices_and_recomputes_the_delivery_fee(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = $this->makeProduct();
        $this->addFarmerStock($product, 3, 300);
        BasketItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'quality' => 3, 'quantity' => 20]);
        BasketItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'quality' => 2, 'quantity' => 5]);
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/orders', $this->orderPayload(
            [['product_id' => $product->id, 'quantity' => '20', 'quality' => 3, 'price' => 1]],
            ['delivery_fee' => 1, 'distance_km' => 999],
        ));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.status', 'awaiting_confirmation')
            ->assertJsonPath('order.delivery_method', 'delivery')
            ->assertJsonPath('order.delivery_fee', '3000')
            ->assertJsonPath('order.total_amount', '33000.00')
            ->assertJsonPath('order.cancel_window_seconds', 20)
            ->assertJsonPath('order.confirmed_at', null)
            ->assertJsonPath('order.cancelled_at', null)
            ->assertJsonPath('order.phone', '07501234567')
            ->assertJsonPath('order.items.0.quality', 3)
            ->assertJsonPath('order.items.0.quantity', 20)
            ->assertJsonPath('order.items.0.unit_price', '1500.00')
            ->assertJsonPath('order.items.0.subtotal', '30000.00')
            ->assertJsonPath('order.items.0.product.title', 'Tomato')
            ->assertJsonPath('order.buyer.id', $buyer->id)
            ->assertJsonPath('order.buyer.profile.user_id', $buyer->id)
            ->assertJsonPath('order.farmers', [])
            ->assertJsonPath('order.feedback', null)
            ->assertJsonStructure(['order' => ['id', 'created_at', 'server_time', 'address', 'city', 'cancel_reason']]);

        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}Z$/', $response->json('order.server_time'));
        $this->assertEqualsWithDelta(12.4, $response->json('order.distance_km'), 0.01);

        // Only the ordered product/quality left the basket
        $this->assertDatabaseMissing('basket_items', ['buyer_id' => $buyer->id, 'quality' => 3]);
        $this->assertDatabaseHas('basket_items', ['buyer_id' => $buyer->id, 'quality' => 2, 'quantity' => 5]);

        // The order now holds its stock
        $this->getJson("/api/products/{$product->id}")
            ->assertJsonPath('product.good_quantity', 280)
            ->assertJsonPath('product.total_quantity', 280)
            ->assertJsonPath('product.price', '1500.00');
    }

    public function test_pickup_is_free_and_needs_no_location(): void
    {
        $product = $this->makeProduct();
        $this->addFarmerStock($product, 2, 50);
        $buyer = User::factory()->buyer()->create();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/orders', [
            'items'           => [['product_id' => $product->id, 'quantity' => 10, 'quality' => 2]],
            'address'         => 'Street 12',
            'city'            => 'Erbil',
            'delivery_method' => 'pickup',
            'delivery_fee'    => 5000,
        ])
            ->assertCreated()
            ->assertJsonPath('order.delivery_fee', '0')
            ->assertJsonPath('order.total_amount', '12000.00')
            ->assertJsonPath('order.latitude', null)
            // Missing phone falls back to the user's phone
            ->assertJsonPath('order.phone', $buyer->phone);
    }

    public function test_delivery_needs_a_location_and_a_matching_tier(): void
    {
        $product = $this->makeProduct();
        $this->addFarmerStock($product, 3, 50);
        Sanctum::actingAs(User::factory()->buyer()->create());
        $items = [['product_id' => $product->id, 'quantity' => 1, 'quality' => 3]];

        $this->postJson('/api/orders', $this->orderPayload($items, ['latitude' => null, 'longitude' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->postJson('/api/orders', $this->orderPayload($items, $this->pointKmFromWarehouse(250)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('delivery_method');

        $this->postJson('/api/orders', $this->orderPayload($items, ['delivery_method' => 'drone']))
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stock_changed_returns_409_and_saves_nothing(): void
    {
        $buyer = User::factory()->buyer()->create();
        $tomato = $this->makeProduct();
        $potato = $this->makeProduct(['title' => 'Potato']);
        $this->addFarmerStock($tomato, 3, 100);
        $this->addFarmerStock($potato, 3, 12.5);
        BasketItem::create(['buyer_id' => $buyer->id, 'product_id' => $tomato->id, 'quality' => 3, 'quantity' => 5]);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_id' => $tomato->id, 'quantity' => 5, 'quality' => 3],
            ['product_id' => $potato->id, 'quantity' => 20, 'quality' => 3],
            ['product_id' => $potato->id, 'quantity' => 1, 'quality' => 1],
        ]))
            ->assertStatus(409)
            ->assertExactJson([
                'code'    => 'stock_changed',
                'message' => 'Some items are no longer available in that quantity.',
                'items'   => [
                    ['product_id' => $potato->id, 'quality' => 3, 'title' => 'Potato', 'requested' => 20, 'available' => 12],
                    ['product_id' => $potato->id, 'quality' => 1, 'title' => 'Potato', 'requested' => 1, 'available' => 0],
                ],
            ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseHas('basket_items', ['buyer_id' => $buyer->id, 'product_id' => $tomato->id, 'quantity' => 5]);
    }

    public function test_two_orders_cannot_take_the_same_last_kilos(): void
    {
        $product = $this->makeProduct();
        $this->addFarmerStock($product, 3, 6);
        $this->addFarmerStock($product, 3, 4); // a second farmer: 10 kg in total
        $items = [['product_id' => $product->id, 'quantity' => 10, 'quality' => 3]];

        Sanctum::actingAs(User::factory()->buyer()->create());
        $this->postJson('/api/orders', $this->orderPayload($items))->assertCreated();

        Sanctum::actingAs(User::factory()->buyer()->create());
        $this->postJson('/api/orders', $this->orderPayload([['product_id' => $product->id, 'quantity' => 1, 'quality' => 3]]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'stock_changed')
            ->assertJsonPath('items.0.available', 0);

        $this->assertSame(1, Order::count());
    }

    public function test_rejected_orders_release_their_stock(): void
    {
        $product = $this->makeProduct();
        $this->addFarmerStock($product, 3, 10);
        Sanctum::actingAs(User::factory()->buyer()->create());
        $orderId = $this->postJson('/api/orders', $this->orderPayload([['product_id' => $product->id, 'quantity' => 10, 'quality' => 3]]))
            ->json('order.id');

        $this->getJson("/api/products/{$product->id}")->assertJsonPath('product.good_quantity', 0);

        Order::whereKey($orderId)->update(['status' => 'rejected']);

        $this->getJson('/api/products')->assertJsonPath('products.0.good_quantity', 10);
    }

    public function test_only_buyers_can_place_orders(): void
    {
        Sanctum::actingAs(User::factory()->farmer()->create());

        $this->postJson('/api/orders', [])->assertForbidden();
    }
}
