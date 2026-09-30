<?php

namespace Tests\Feature;

use App\Models\FarmerProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FarmerStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_quality_keeps_its_own_row(): void
    {
        $product = $this->makeProduct();
        Sanctum::actingAs(User::factory()->farmer()->create());

        $this->postJson('/api/farmer/products', ['product_id' => $product->id, 'quantity' => 100, 'quality' => 3, 'rating' => 3])->assertOk();
        $this->postJson('/api/farmer/products', ['product_id' => $product->id, 'quantity' => 50, 'quality' => 1, 'rating' => 1])->assertOk();
        $this->postJson('/api/farmer/products', ['product_id' => $product->id, 'quantity' => 5, 'quality' => 3, 'rating' => 3])->assertOk();

        $this->assertSame(2, FarmerProduct::count());
        $this->getJson("/api/products/{$product->id}")
            ->assertJsonPath('product.good_quantity', 105)
            ->assertJsonPath('product.bad_quantity', 50);
        $this->assertEquals(155, $product->fresh()->getRawOriginal('total_quantity'));
    }

    public function test_quality_alone_is_accepted_as_the_rating(): void
    {
        $product = $this->makeProduct();
        Sanctum::actingAs(User::factory()->farmer()->create());

        $this->postJson('/api/farmer/products', ['product_id' => $product->id, 'quantity' => 10, 'quality' => 2])->assertOk();

        $this->assertDatabaseHas('farmer_products', ['product_id' => $product->id, 'rating' => 2, 'quantity' => 10]);
    }

    public function test_changing_a_rows_quality_merges_it_into_the_existing_row(): void
    {
        $product = $this->makeProduct();
        $farmer = User::factory()->farmer()->create();
        $good = $this->addFarmerStock($product, 3, 100, $farmer);
        $normal = $this->addFarmerStock($product, 2, 30, $farmer);
        Sanctum::actingAs($farmer);

        $this->patchJson("/api/farmer/products/{$normal->id}", ['quantity' => 40, 'quality' => 3, 'rating' => 3])
            ->assertOk()
            ->assertJsonPath('farmer_product.id', $good->id);

        $this->assertDatabaseMissing('farmer_products', ['id' => $normal->id]);
        $this->assertDatabaseHas('farmer_products', ['id' => $good->id, 'rating' => 3]);
        $this->getJson("/api/products/{$product->id}")->assertJsonPath('product.good_quantity', 140);
    }

    public function test_admin_rating_an_unrated_row_merges_it(): void
    {
        $product = $this->makeProduct();
        $farmer = User::factory()->farmer()->create();
        $good = $this->addFarmerStock($product, 3, 20, $farmer);
        $unrated = FarmerProduct::create(['farmer_id' => $farmer->farmer->id, 'product_id' => $product->id, 'quantity' => 7, 'rating' => null]);

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson("/api/admin/farmer-products/{$unrated->id}/rate", ['rating' => 3])->assertOk();

        $this->assertSame(1, FarmerProduct::count());
        $this->assertEquals(27, $good->fresh()->quantity);
    }

    public function test_farmers_cannot_touch_other_farmers_rows(): void
    {
        $product = $this->makeProduct();
        $row = $this->addFarmerStock($product, 3, 20);

        Sanctum::actingAs(User::factory()->farmer()->create());

        $this->patchJson("/api/farmer/products/{$row->id}", ['quantity' => 1])->assertNotFound();
        $this->deleteJson("/api/farmer/products/{$row->id}")->assertNotFound();
        $this->assertEquals(20, $row->fresh()->quantity);
    }
}
