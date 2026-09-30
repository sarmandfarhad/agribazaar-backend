<?php

namespace App\Services;

use App\Models\FarmerProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * A farmer's stock of a product, one row per quality (farmer_products.rating: 3 good, 2 normal, 1 bad;
 * null while not yet rated). Used by both the farmer and the admin endpoints.
 */
class FarmerStockService
{
    /**
     * Adds kg to the farmer's row for this product and quality, creating it if needed.
     */
    public function add(int $farmerId, int $productId, ?int $rating, float $quantity): FarmerProduct
    {
        return DB::transaction(function () use ($farmerId, $productId, $rating, $quantity) {
            $row = $this->rowFor($farmerId, $productId, $rating);

            if ($row) {
                $row->quantity += $quantity;
                $row->save();
            } else {
                $row = FarmerProduct::create([
                    'farmer_id'  => $farmerId,
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                    'rating'     => $rating,
                ]);
            }

            $this->syncProductTotal($productId);

            return $row;
        });
    }

    /**
     * Sets a row's quantity and/or quality. Moving to a quality the farmer already has for this
     * product merges the two rows, so each quality keeps a single row.
     */
    public function update(FarmerProduct $row, ?float $quantity, ?int $rating, bool $changeRating): FarmerProduct
    {
        return DB::transaction(function () use ($row, $quantity, $rating, $changeRating) {
            if ($quantity !== null) {
                $row->quantity = $quantity;
            }

            if ($changeRating && $rating !== $row->rating) {
                $row = $this->moveToRating($row, $rating);
            }

            $row->save();
            $this->syncProductTotal($row->product_id);

            return $row;
        });
    }

    public function remove(FarmerProduct $row): void
    {
        DB::transaction(function () use ($row) {
            $row->delete();
            $this->syncProductTotal($row->product_id);
        });
    }

    /**
     * Keeps products.total_quantity equal to the raw farmer stock (the API shows available stock instead).
     */
    public function syncProductTotal(int $productId): void
    {
        Product::whereKey($productId)->update([
            'total_quantity' => FarmerProduct::where('product_id', $productId)->sum('quantity'),
        ]);
    }

    private function moveToRating(FarmerProduct $row, ?int $rating): FarmerProduct
    {
        $existing = $this->rowFor($row->farmer_id, $row->product_id, $rating, exceptId: $row->id);

        if (! $existing) {
            $row->rating = $rating;

            return $row;
        }

        $existing->quantity += $row->quantity;
        $row->delete();

        return $existing;
    }

    private function rowFor(int $farmerId, int $productId, ?int $rating, ?int $exceptId = null): ?FarmerProduct
    {
        return FarmerProduct::query()
            ->where('farmer_id', $farmerId)
            ->where('product_id', $productId)
            ->when($rating === null,
                fn ($q) => $q->whereNull('rating'),
                fn ($q) => $q->where('rating', $rating))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->lockForUpdate()
            ->first();
    }
}
