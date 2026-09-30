<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliverySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_return_the_seeded_warehouse_and_sorted_tiers(): void
    {
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->getJson('/api/delivery/settings')
            ->assertOk()
            ->assertExactJson([
                'warehouse' => [
                    'name'      => 'AgriBazaar Warehouse',
                    'address'   => '100m Street, Erbil',
                    'latitude'  => 36.1911,
                    'longitude' => 44.0092,
                ],
                'tiers' => [
                    ['min_km' => 0,  'max_km' => 10,  'fee' => 2000],
                    ['min_km' => 10, 'max_km' => 50,  'fee' => 3000],
                    ['min_km' => 50, 'max_km' => 200, 'fee' => 5000],
                ],
            ]);
    }

    public static function boundaries(): array
    {
        return [
            '0 km matches the tier starting at 0' => [0, 2000],
            '10 km is the top of tier 1'          => [10, 2000],
            '10.01 km is tier 2'                  => [10.01, 3000],
            '200 km is the top of tier 3'         => [200, 5000],
            '200.01 km is out of range'           => [200.01, null],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_fee_tier_boundaries(float $km, ?int $fee): void
    {
        $this->assertSame($fee, app(DeliveryService::class)->feeForDistance($km));
    }

    public function test_haversine_distance_is_measured_from_the_warehouse(): void
    {
        $point = $this->pointKmFromWarehouse(12.4);

        $this->assertEqualsWithDelta(
            12.4,
            app(DeliveryService::class)->distanceFromWarehouseKm($point['latitude'], $point['longitude']),
            0.001,
        );
    }

    public function test_admin_can_replace_warehouse_and_tiers(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $body = [
            'warehouse' => ['name' => 'North Hub', 'address' => 'Road 1', 'latitude' => 36.2, 'longitude' => 44.1],
            'tiers'     => [
                ['min_km' => 20, 'max_km' => 100, 'fee' => 4000],
                ['min_km' => 0, 'max_km' => 20, 'fee' => 1000],
            ],
        ];

        $this->putJson('/api/admin/delivery/settings', $body)
            ->assertOk()
            ->assertJsonPath('warehouse.name', 'North Hub')
            ->assertJsonPath('tiers.0.min_km', 0)
            ->assertJsonPath('tiers.1.fee', 4000)
            ->assertJsonCount(2, 'tiers');

        $this->getJson('/api/admin/delivery/settings')->assertJsonPath('warehouse.address', 'Road 1');
        $this->assertDatabaseCount('delivery_tiers', 2);
        $this->assertDatabaseCount('delivery_settings', 1);
    }

    public function test_admin_update_rejects_bad_and_overlapping_tiers(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $warehouse = ['name' => 'W', 'address' => 'A', 'latitude' => 36.2, 'longitude' => 44.1];

        $this->putJson('/api/admin/delivery/settings', [
            'warehouse' => $warehouse,
            'tiers'     => [['min_km' => 10, 'max_km' => 10, 'fee' => 1000]],
        ])->assertStatus(422)->assertJsonValidationErrors('tiers');

        $this->putJson('/api/admin/delivery/settings', [
            'warehouse' => $warehouse,
            'tiers'     => [
                ['min_km' => 0, 'max_km' => 15, 'fee' => 1000],
                ['min_km' => 10, 'max_km' => 50, 'fee' => 2000],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('tiers');

        // Nothing was changed
        $this->assertDatabaseCount('delivery_tiers', 3);
    }

    public function test_non_admins_cannot_use_the_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->buyer()->create());

        $this->getJson('/api/admin/delivery/settings')->assertForbidden();
        $this->putJson('/api/admin/delivery/settings', [])->assertForbidden();
    }
}
