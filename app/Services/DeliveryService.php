<?php

namespace App\Services;

use App\Models\DeliverySetting;
use App\Models\DeliveryTier;
use App\Support\Num;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function warehouse(): ?DeliverySetting
    {
        return DeliverySetting::query()->orderBy('id')->first();
    }

    /**
     * @return Collection<int, DeliveryTier>
     */
    public function tiers(): Collection
    {
        return DeliveryTier::query()->orderBy('min_km')->get();
    }

    /**
     * The shape GET /api/delivery/settings returns.
     */
    public function settingsPayload(): array
    {
        $warehouse = $this->warehouse();

        return [
            'warehouse' => $warehouse ? [
                'name'      => $warehouse->warehouse_name,
                'address'   => $warehouse->warehouse_address,
                'latitude'  => $warehouse->warehouse_latitude,
                'longitude' => $warehouse->warehouse_longitude,
            ] : null,
            'tiers' => $this->tiers()->map(fn (DeliveryTier $tier) => [
                'min_km' => Num::clean($tier->min_km),
                'max_km' => Num::clean($tier->max_km),
                'fee'    => $tier->fee,
            ])->values()->all(),
        ];
    }

    /**
     * Replaces the warehouse and the whole tier list.
     *
     * @param  array{name: string, address: string, latitude: float|string, longitude: float|string}  $warehouse
     * @param  array<int, array{min_km: float, max_km: float, fee: int}>  $tiers
     */
    public function replaceSettings(array $warehouse, array $tiers): void
    {
        DB::transaction(function () use ($warehouse, $tiers) {
            $settings = DeliverySetting::query()->orderBy('id')->lockForUpdate()->first() ?? new DeliverySetting;
            $settings->fill([
                'warehouse_name'      => $warehouse['name'],
                'warehouse_address'   => $warehouse['address'],
                'warehouse_latitude'  => (float) $warehouse['latitude'],
                'warehouse_longitude' => (float) $warehouse['longitude'],
            ])->save();

            DeliveryTier::query()->delete();
            foreach ($tiers as $tier) {
                DeliveryTier::create($tier);
            }
        });
    }

    /**
     * Straight-line (haversine) distance in km from the warehouse, or null if no warehouse is set.
     */
    public function distanceFromWarehouseKm(float $latitude, float $longitude): ?float
    {
        $warehouse = $this->warehouse();

        if (! $warehouse) {
            return null;
        }

        return $this->haversineKm(
            $warehouse->warehouse_latitude,
            $warehouse->warehouse_longitude,
            $latitude,
            $longitude,
        );
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Fee in whole IQD for a distance, or null when no tier covers it (delivery unavailable).
     * A tier matches when min_km < distance <= max_km; the tier starting at 0 also matches exactly 0.
     */
    public function feeForDistance(float $distanceKm): ?int
    {
        foreach ($this->tiers() as $tier) {
            $matches = ($distanceKm > $tier->min_km && $distanceKm <= $tier->max_km)
                || ($tier->min_km == 0 && $distanceKm == 0);

            if ($matches) {
                return $tier->fee;
            }
        }

        return null;
    }
}
