<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function adminReads(): array
    {
        return [
            ['/api/admin/users'],
            ['/api/admin/products'],
            ['/api/admin/categories'],
            ['/api/admin/orders'],
            ['/api/admin/feedback'],
            ['/api/admin/farmer-products'],
            ['/api/admin/delivery/settings'],
        ];
    }

    #[DataProvider('adminReads')]
    public function test_admin_endpoints_are_closed_to_buyers_and_farmers(string $url): void
    {
        Sanctum::actingAs(User::factory()->buyer()->create());
        $this->getJson($url)->assertForbidden()->assertJsonStructure(['message']);

        Sanctum::actingAs(User::factory()->farmer()->create());
        $this->getJson($url)->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson($url)->assertOk();
    }

    public function test_farmer_endpoints_are_closed_to_buyers(): void
    {
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->getJson('/api/farmer/products')->assertForbidden();
        $this->patchJson('/api/farmer/products/1', ['quantity' => 1])->assertForbidden();
        $this->getJson('/api/orders/farmer')->assertForbidden();
        $this->postJson('/api/orders/1/respond', ['status' => 'accepted'])->assertForbidden();
    }

    public function test_buyer_endpoints_are_closed_to_farmers(): void
    {
        Sanctum::actingAs(User::factory()->farmer()->create());

        $this->getJson('/api/orders/buyer')->assertForbidden();
        $this->postJson('/api/orders/1/cancel')->assertForbidden();
    }
}
