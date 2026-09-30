<?php

namespace App\Services;

use App\Models\FarmerProduct;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Computes how many kg of each product and quality can still be ordered.
 *
 * Farmer stock (farmer_products.quantity) is never reduced by the order lifecycle,
 * so every order that is still alive holds its quantity: available = farmer stock
 * minus the quantities in all orders that are not cancelled or rejected.
 */
class StockService
{
    /** Order statuses whose quantities no longer hold stock. */
    public const RELEASED_STATUSES = ['cancelled', 'rejected'];

    public const QUALITIES = [3, 2, 1];

    /**
     * Available kg per quality for each product.
     *
     * @param  iterable<int>  $productIds
     * @return array<int, array<int, float>>  [product_id => [quality => kg]]
     */
    public function availableFor(iterable $productIds): array
    {
        $ids = collect($productIds)->map(fn ($id) => (int) $id)->unique()->values();

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = array_fill_keys(self::QUALITIES, 0.0);
        }

        if ($ids->isEmpty()) {
            return $result;
        }

        $farmerStock = FarmerProduct::query()
            ->whereIn('product_id', $ids)
            ->whereIn('rating', self::QUALITIES)
            ->groupBy('product_id', 'rating')
            ->selectRaw('product_id, rating as quality, SUM(quantity) as total')
            ->get();

        $held = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $ids)
            ->whereNotIn('orders.status', self::RELEASED_STATUSES)
            ->groupBy('order_items.product_id', 'order_items.quality')
            ->selectRaw('order_items.product_id as product_id, order_items.quality as quality, SUM(order_items.quantity) as total')
            ->get();

        foreach ($farmerStock as $row) {
            $result[(int) $row->product_id][(int) $row->quality] += (float) $row->total;
        }

        foreach ($held as $row) {
            if (isset($result[(int) $row->product_id][(int) $row->quality])) {
                $result[(int) $row->product_id][(int) $row->quality] -= (float) $row->total;
            }
        }

        foreach ($result as $productId => $byQuality) {
            foreach ($byQuality as $quality => $kg) {
                $result[$productId][$quality] = max(0.0, round($kg, 2));
            }
        }

        return $result;
    }

    public function available(int $productId, int $quality): float
    {
        return $this->availableFor([$productId])[$productId][$quality] ?? 0.0;
    }

    /**
     * Computes availability for many products with two queries and stores it on each
     * model, so serializing a list doesn't run two queries per product.
     *
     * @param  Collection<int, Product>|iterable<Product>  $products
     */
    public function preload(iterable $products): void
    {
        $products = collect($products)->filter();
        $available = $this->availableFor($products->pluck('id'));

        foreach ($products as $product) {
            $product->setAvailableStock($available[$product->id]);
        }
    }
}
