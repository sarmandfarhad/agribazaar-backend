<?php

namespace App\Services;

use App\Models\BasketItem;
use App\Support\Num;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class BasketService
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * Adds kg to the buyer's line for this product and quality, creating the line if needed.
     */
    public function add(int $buyerId, int $productId, int $quality, int $quantity): BasketItem
    {
        return DB::transaction(function () use ($buyerId, $productId, $quality, $quantity) {
            $item = BasketItem::query()
                ->where('buyer_id', $buyerId)
                ->where('product_id', $productId)
                ->where('quality', $quality)
                ->lockForUpdate()
                ->first();

            if ($item) {
                $item->increment('quantity', $quantity);

                return $item;
            }

            try {
                // A savepoint, so a lost race doesn't abort the surrounding transaction on PostgreSQL
                return DB::transaction(fn () => BasketItem::create([
                    'buyer_id'   => $buyerId,
                    'product_id' => $productId,
                    'quality'    => $quality,
                    'quantity'   => $quantity,
                ]));
            } catch (UniqueConstraintViolationException) {
                // Another request created the same line in the meantime: add to it instead
                $item = BasketItem::query()
                    ->where('buyer_id', $buyerId)
                    ->where('product_id', $productId)
                    ->where('quality', $quality)
                    ->lockForUpdate()
                    ->firstOrFail();
                $item->increment('quantity', $quantity);

                return $item;
            }
        });
    }

    /**
     * The whole basket in the shape every basket route returns.
     */
    public function payload(int $buyerId): array
    {
        $items = BasketItem::query()
            ->where('buyer_id', $buyerId)
            ->with(['product.category', 'product.images'])
            ->orderBy('id')
            ->get()
            ->filter(fn (BasketItem $item) => $item->product !== null);

        $this->stock->preload($items->pluck('product'));

        return [
            'items' => $items->map(fn (BasketItem $item) => [
                'id'         => $item->id,
                'product_id' => $item->product_id,
                'quality'    => $item->quality,
                'quantity'   => $item->quantity,
                'unit_price' => Num::clean($item->product->priceFor($item->quality)),
                'available'  => (int) floor($item->product->availableFor($item->quality)),
                'product'    => $item->product->toArray(),
            ])->values()->all(),
        ];
    }
}
