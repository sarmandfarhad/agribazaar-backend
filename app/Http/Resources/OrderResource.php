<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\StockService;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The order object every order endpoint returns.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * Relations the resource reads. Load them with Order::load()/with() before serializing.
     */
    public const RELATIONS = [
        'items.product.category',
        'items.product.images',
        'buyer.farmer',
        'buyer.buyer',
        'farmers.farmer',
        'farmers.buyer',
        'feedback',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'buyer_id'              => $this->buyer_id,
            'total_amount'          => $this->total_amount,
            'status'                => $this->status,
            'address'               => $this->address,
            'city'                  => $this->city,
            'phone'                 => $this->phone,
            'cancel_reason'         => $this->cancel_reason,
            'created_at'            => $this->created_at?->toJSON(),
            'confirmed_at'          => $this->confirmed_at?->toJSON(),
            'cancelled_at'          => $this->cancelled_at?->toJSON(),
            'cancel_window_seconds' => (int) $this->cancel_window_seconds,
            'server_time'           => now()->toJSON(),
            'delivery_method'       => $this->delivery_method,
            'delivery_fee'          => (string) (int) $this->delivery_fee,
            'latitude'              => $this->latitude,
            'longitude'             => $this->longitude,
            'distance_km'           => $this->distance_km,
            'items'                 => $this->items->map(fn (OrderItem $item) => [
                'id'         => $item->id,
                'order_id'   => $item->order_id,
                'product_id' => $item->product_id,
                'quality'    => $item->quality,
                'quantity'   => $item->quantity,
                'unit_price' => $item->price,
                'subtotal'   => Num::money((float) $item->price * $item->quantity),
                'product'    => $item->product?->toArray(),
            ])->values()->all(),
            'buyer'                 => $this->buyer?->toArrayWithProfile(),
            'farmers'               => $this->farmers->map(fn (User $farmer) => $farmer->toArrayWithProfile())->values()->all(),
            'feedback'              => $this->feedback->sortByDesc('id')->first()?->toArray(),
        ];
    }

    /**
     * Loads everything the resource needs, including product availability in two queries.
     */
    public static function prepare(Order|Collection $orders): void
    {
        $orders = $orders instanceof Order ? collect([$orders]) : $orders;

        if ($orders->isEmpty()) {
            return;
        }

        $orders->each(fn (Order $order) => $order->loadMissing(self::RELATIONS));

        app(StockService::class)->preload(
            $orders->flatMap(fn (Order $order) => $order->items->pluck('product'))->filter()->unique('id')
        );
    }

    /**
     * One order as a plain array (no "data" wrapper), ready for {"order": ...}.
     */
    public static function single(Order $order): array
    {
        self::prepare($order);

        return (new self($order))->resolve();
    }

    /**
     * Many orders as plain arrays, ready for {"orders": [...]}.
     */
    public static function many(Collection $orders): array
    {
        self::prepare($orders);

        return $orders->map(fn (Order $order) => (new self($order))->resolve())->values()->all();
    }
}
