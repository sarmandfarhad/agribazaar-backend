<?php

namespace Tests;

use App\Models\FarmerProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** The seeded warehouse (see the delivery settings migration). */
    protected const WAREHOUSE_LAT = 36.1911;

    protected const WAREHOUSE_LNG = 44.0092;

    protected function makeProduct(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'title'        => 'Tomato',
            'price_good'   => 1500,
            'price_normal' => 1200,
            'price_bad'    => 800,
            'quantity'       => 0,
            'total_quantity' => 0,
            'information'    => 'Fresh tomatoes',
            'status'       => 'active',
        ]);
    }

    /**
     * Gives a (new) farmer $kg of the product at the given quality.
     */
    protected function addFarmerStock(Product $product, int $quality, float $kg, ?User $farmer = null): FarmerProduct
    {
        $farmer ??= User::factory()->farmer()->create();

        return FarmerProduct::create([
            'farmer_id'  => $farmer->farmer->id,
            'product_id' => $product->id,
            'quantity'   => $kg,
            'rating'     => $quality,
        ]);
    }

    /**
     * A point due north of the warehouse, $km away in a straight line.
     *
     * @return array{latitude: float, longitude: float}
     */
    protected function pointKmFromWarehouse(float $km): array
    {
        return [
            'latitude'  => self::WAREHOUSE_LAT + rad2deg($km / 6371.0),
            'longitude' => self::WAREHOUSE_LNG,
        ];
    }

    /**
     * A valid POST /api/orders body for delivery ~12.4 km away (the 3000 IQD tier).
     */
    protected function orderPayload(array $items, array $overrides = []): array
    {
        return array_merge([
            'items'           => $items,
            'address'         => 'Street 12',
            'city'            => 'Erbil',
            'phone'           => '07501234567',
            'delivery_method' => 'delivery',
        ], $this->pointKmFromWarehouse(12.4), $overrides);
    }
}
