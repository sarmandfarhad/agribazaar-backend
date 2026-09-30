<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The response shapes the app reads from endpoints that already existed.
 */
class ExistingEndpointsContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_signup_return_user_with_profile_and_token(): void
    {
        $this->postJson('/api/auth/buyer/signup', [
            'phone' => '07500000001', 'password' => 'secret1', 'password_confirmation' => 'secret1',
            'first_name' => 'A', 'second_name' => 'B', 'address' => 'Street', 'city' => 'Erbil', 'business_name' => 'Shop',
        ])->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'user_type', 'profile' => ['first_name']]]);

        $this->postJson('/api/auth/farmer/signup', [
            'phone' => '07500000002', 'password' => 'secret1', 'password_confirmation' => 'secret1',
            'first_name' => 'A', 'second_name' => 'B', 'address' => 'Street', 'city' => 'Erbil',
        ])->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'profile' => ['first_name']]]);

        $buyer = User::factory()->buyer()->create();
        $token = $this->postJson('/api/auth/login', ['phone' => $buyer->phone, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'phone', 'user_type', 'status', 'profile' => ['first_name']]])
            ->json('token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.id', $buyer->id)
            ->assertJsonStructure(['user' => ['profile' => ['first_name']]]);

        // Each real request starts with a fresh user; in a test the guard would reuse the one
        // GET /api/user attached "profile" to, and saving it would fail
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/logout')->assertOk();
    }

    public function test_products_include_every_field_the_app_reads(): void
    {
        $category = Category::create(['name' => 'Vegetables', 'isActive' => true]);
        $product = $this->makeProduct(['category_id' => $category->id]);
        $this->addFarmerStock($product, 3, 30);
        $this->addFarmerStock($product, 2, 20);
        $this->addFarmerStock($product, 1, 10.5);

        $fields = ['id', 'title', 'price', 'price_good', 'price_normal', 'price_bad', 'good_quantity', 'normal_quantity',
            'bad_quantity', 'total_quantity', 'image_url', 'images', 'category' => ['id', 'name']];

        $this->getJson('/api/products?limit=500&per_page=500&page_size=500&take=500&status=all')
            ->assertOk()
            ->assertJsonStructure(['products' => [$fields]])
            ->assertJsonPath('products.0.price', '1500.00')
            ->assertJsonPath('products.0.good_quantity', 30)
            ->assertJsonPath('products.0.normal_quantity', 20)
            ->assertJsonPath('products.0.bad_quantity', 10.5)
            ->assertJsonPath('products.0.total_quantity', 60.5)
            ->assertJsonMissingPath('products.0.farmer_products');

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonStructure(['product' => $fields]);
    }

    public function test_categories_list_and_show(): void
    {
        $category = Category::create(['name' => 'Fruit', 'isActive' => true]);

        $this->getJson('/api/categories')->assertOk()->assertJsonPath('categories.0.id', $category->id);
        $this->getJson("/api/categories/{$category->id}")->assertOk()->assertJsonPath('category.id', $category->id);
    }

    public function test_wishlist_round_trip(): void
    {
        $product = $this->makeProduct();
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->postJson('/api/wishlist', ['product_id' => $product->id])->assertOk();
        $this->getJson('/api/wishlist')
            ->assertOk()
            ->assertJsonPath('wishlist.0.product_id', $product->id)
            ->assertJsonStructure(['wishlist' => [['product' => ['price', 'good_quantity', 'total_quantity']]]]);
        $this->deleteJson("/api/wishlist/{$product->id}")->assertOk();
    }

    public function test_farmer_products_crud(): void
    {
        $product = $this->makeProduct();
        Sanctum::actingAs(User::factory()->farmer()->create());

        $this->postJson('/api/farmer/products', ['product_id' => $product->id, 'quantity' => 40, 'quality' => 3, 'rating' => 3])
            ->assertOk();

        $id = $this->getJson('/api/farmer/products')
            ->assertOk()
            ->assertJsonPath('farmer_products.0.product_id', $product->id)
            ->json('farmer_products.0.id');

        $this->patchJson("/api/farmer/products/{$id}", ['quantity' => 25, 'quality' => 2, 'rating' => 2])->assertOk();
        $this->assertDatabaseHas('farmer_products', ['id' => $id, 'rating' => 2]);
        $this->getJson("/api/products/{$product->id}")->assertJsonPath('product.normal_quantity', 25);

        $this->deleteJson("/api/farmer/products/{$id}")->assertOk();
        $this->assertDatabaseMissing('farmer_products', ['id' => $id]);
    }
}
