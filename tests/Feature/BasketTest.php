<?php

namespace Tests\Feature;

use App\Models\BasketItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BasketTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_the_same_product_and_quality_merges_into_one_line(): void
    {
        $product = $this->makeProduct();
        $this->addFarmerStock($product, 3, 300);
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->postJson('/api/basket/items', ['product_id' => $product->id, 'quality' => 3, 'quantity' => 20])
            ->assertOk();

        $response = $this->postJson('/api/basket/items', ['product_id' => $product->id, 'quality' => '3', 'quantity' => '5'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'basket.items')
            ->assertJsonPath('basket.items.0.product_id', $product->id)
            ->assertJsonPath('basket.items.0.quality', 3)
            ->assertJsonPath('basket.items.0.quantity', 25)
            ->assertJsonPath('basket.items.0.unit_price', 1500)
            ->assertJsonPath('basket.items.0.available', 300)
            ->assertJsonPath('basket.items.0.product.title', 'Tomato');

        $this->assertArrayHasKey('images', $response->json('basket.items.0.product'));
        $this->assertArrayHasKey('image_url', $response->json('basket.items.0.product'));

        // A different quality is a separate line
        $this->postJson('/api/basket/items', ['product_id' => $product->id, 'quality' => 2, 'quantity' => 1])
            ->assertJsonCount(2, 'basket.items');
    }

    public function test_adding_more_than_available_is_allowed(): void
    {
        $product = $this->makeProduct();
        $this->addFarmerStock($product, 3, 10);
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->postJson('/api/basket/items', ['product_id' => $product->id, 'quality' => 3, 'quantity' => 50])
            ->assertOk()
            ->assertJsonPath('basket.items.0.quantity', 50)
            ->assertJsonPath('basket.items.0.available', 10);
    }

    public function test_patch_to_zero_or_less_removes_the_line(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = $this->makeProduct();
        $line = BasketItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'quality' => 3, 'quantity' => 4]);
        Sanctum::actingAs($buyer);

        $this->patchJson("/api/basket/items/{$line->id}", ['quantity' => 7])
            ->assertOk()
            ->assertJsonPath('basket.items.0.quantity', 7);

        $this->patchJson("/api/basket/items/{$line->id}", ['quantity' => 0])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'basket.items');

        $this->assertDatabaseMissing('basket_items', ['id' => $line->id]);
    }

    public function test_another_buyers_line_returns_404(): void
    {
        $owner = User::factory()->buyer()->create();
        $product = $this->makeProduct();
        $line = BasketItem::create(['buyer_id' => $owner->id, 'product_id' => $product->id, 'quality' => 3, 'quantity' => 4]);

        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->patchJson("/api/basket/items/{$line->id}", ['quantity' => 1])
            ->assertNotFound()
            ->assertJsonStructure(['message']);
        $this->deleteJson("/api/basket/items/{$line->id}")->assertNotFound();

        $this->assertDatabaseHas('basket_items', ['id' => $line->id, 'quantity' => 4]);
    }

    public function test_delete_removes_the_line(): void
    {
        $buyer = User::factory()->buyer()->create();
        $product = $this->makeProduct();
        $line = BasketItem::create(['buyer_id' => $buyer->id, 'product_id' => $product->id, 'quality' => 3, 'quantity' => 4]);
        Sanctum::actingAs($buyer);

        $this->deleteJson("/api/basket/items/{$line->id}")
            ->assertOk()
            ->assertJsonCount(0, 'basket.items');
    }

    public function test_validation_rejects_inactive_products_bad_quality_and_zero_quantity(): void
    {
        $inactive = $this->makeProduct(['status' => 'inactive']);
        $product = $this->makeProduct();
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->postJson('/api/basket/items', ['product_id' => $inactive->id, 'quality' => 3, 'quantity' => 1])
            ->assertStatus(422);
        $this->postJson('/api/basket/items', ['product_id' => $product->id, 'quality' => 4, 'quantity' => 1])
            ->assertStatus(422);
        $this->postJson('/api/basket/items', ['product_id' => $product->id, 'quality' => 3, 'quantity' => 0])
            ->assertStatus(422);
        $this->postJson('/api/basket/items', ['product_id' => $product->id, 'quality' => 3, 'quantity' => 1.5])
            ->assertStatus(422);
    }

    public function test_non_buyers_get_403(): void
    {
        Sanctum::actingAs(User::factory()->farmer()->create());

        $this->getJson('/api/basket')->assertForbidden()->assertJsonStructure(['message']);
    }

    public function test_guests_get_401_json_without_an_accept_header(): void
    {
        $this->get('/api/basket')->assertUnauthorized()->assertJsonStructure(['message']);
    }
}
