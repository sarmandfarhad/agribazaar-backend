<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmed;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->buyer()->create();
        $this->admin = User::factory()->admin()->create();
        $this->product = $this->makeProduct();
        $this->addFarmerStock($this->product, 3, 100);
    }

    private function placeOrder(): int
    {
        Sanctum::actingAs($this->buyer);

        return $this->postJson('/api/orders', $this->orderPayload([
            ['product_id' => $this->product->id, 'quantity' => 10, 'quality' => 3],
        ]))->assertCreated()->json('order.id');
    }

    public function test_no_one_is_notified_while_the_window_is_open(): void
    {
        $orderId = $this->placeOrder();

        $this->travel(15)->seconds();
        $this->getJson('/api/orders/buyer')->assertJsonPath('orders.0.status', 'awaiting_confirmation');

        $this->assertSame('awaiting_confirmation', Order::find($orderId)->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_orders_are_confirmed_once_and_notified_once(): void
    {
        $orderId = $this->placeOrder();
        $this->travel(21)->seconds();

        // Several reads, each running the confirmation check
        $this->getJson('/api/orders/buyer')
            ->assertJsonPath('orders.0.id', $orderId)
            ->assertJsonPath('orders.0.status', 'confirmed');
        $this->getJson("/api/orders/{$orderId}")->assertJsonPath('order.status', 'confirmed');
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/orders')->assertJsonPath('orders.0.status', 'confirmed');

        // And a racing caller that already loaded the order before it was confirmed
        $this->assertFalse(app(OrderService::class)->confirm($orderId));

        $order = Order::find($orderId);
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->confirmed_at);

        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame($orderId, $this->admin->notifications()->first()->data['order_id']);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_every_admin_gets_one_notification(): void
    {
        Notification::fake();
        $secondAdmin = User::factory()->admin()->create();

        $this->placeOrder();
        $this->travel(21)->seconds();
        app(OrderService::class)->confirmDueOrders();
        app(OrderService::class)->confirmDueOrders();

        Notification::assertSentToTimes($this->admin, OrderConfirmed::class, 1);
        Notification::assertSentToTimes($secondAdmin, OrderConfirmed::class, 1);
        Notification::assertNotSentTo($this->buyer, OrderConfirmed::class);
    }

    public function test_farmers_do_not_see_unconfirmed_orders(): void
    {
        $orderId = $this->placeOrder();
        $farmer = User::factory()->farmer()->create();
        // Assigned directly: the admin endpoint refuses orders that are still awaiting confirmation
        Order::find($orderId)->farmers()->attach($farmer->id);

        Sanctum::actingAs($farmer);
        $this->getJson('/api/orders/farmer')->assertOk()->assertJsonCount(0, 'orders');
        $this->getJson("/api/orders/{$orderId}")->assertNotFound();

        $this->travel(21)->seconds();

        $this->getJson('/api/orders/farmer')
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.status', 'confirmed');
        $this->getJson("/api/orders/{$orderId}")->assertOk()->assertJsonPath('order.id', $orderId);
    }

    public function test_farmers_do_not_see_orders_cancelled_inside_the_window(): void
    {
        $orderId = $this->placeOrder();
        $this->postJson("/api/orders/{$orderId}/cancel", [])->assertOk();

        $farmer = User::factory()->farmer()->create();
        Order::find($orderId)->farmers()->attach($farmer->id);

        Sanctum::actingAs($farmer);
        $this->getJson('/api/orders/farmer')->assertJsonCount(0, 'orders');
        $this->getJson("/api/orders/{$orderId}")->assertNotFound();
    }

    public function test_admin_cannot_assign_farmers_before_confirmation_or_after_cancelling(): void
    {
        $orderId = $this->placeOrder();
        $farmer = User::factory()->farmer()->create();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/orders/{$orderId}/assign-farmers", ['farmer_ids' => [$farmer->id]])
            ->assertStatus(409)
            ->assertJsonPath('code', 'order_not_confirmed');

        $this->travel(21)->seconds();

        $this->postJson("/api/admin/orders/{$orderId}/assign-farmers", ['farmer_ids' => [$farmer->id]])
            ->assertOk()
            ->assertJsonPath('order.status', 'assigned')
            ->assertJsonPath('order.farmers.0.id', $farmer->id)
            ->assertJsonPath('order.farmers.0.profile.user_id', $farmer->id);

        $cancelledId = $this->placeOrder();
        $this->postJson("/api/orders/{$cancelledId}/cancel", [])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/orders/{$cancelledId}/assign-farmers", ['farmer_ids' => [$farmer->id]])
            ->assertStatus(409)
            ->assertJsonPath('code', 'order_cancelled');
    }

    public function test_single_order_is_hidden_from_other_users(): void
    {
        $orderId = $this->placeOrder();

        Sanctum::actingAs(User::factory()->buyer()->create());
        $this->getJson("/api/orders/{$orderId}")->assertNotFound()->assertJsonStructure(['message']);

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/orders/{$orderId}")->assertOk()->assertJsonPath('success', true);
    }

    public function test_cron_route_needs_the_secret(): void
    {
        $this->placeOrder();
        $this->travel(21)->seconds();

        $this->getJson('/api/cron/confirm-orders')->assertNotFound();

        config(['orders.cron_secret' => 's3cret']);

        $this->getJson('/api/cron/confirm-orders', ['Authorization' => 'Bearer wrong'])->assertNotFound();
        $this->getJson('/api/cron/confirm-orders', ['Authorization' => 'Bearer s3cret'])
            ->assertOk()
            ->assertJsonPath('confirmed', 1);
    }
}
