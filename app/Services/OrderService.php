<?php

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Jobs\ConfirmOrder;
use App\Models\BasketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmed;
use App\Support\Num;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderService
{
    public function __construct(
        private StockService $stock,
        private DeliveryService $delivery,
        private BasketService $basket,
    ) {
    }

    /**
     * Confirms every order whose cancel window has ended. Called at the start of each order
     * read and checkout ("check on read"), because Vercel runs no queue worker or scheduler.
     *
     * @return int how many orders this call confirmed
     */
    public function confirmDueOrders(): int
    {
        // Only orders placed in the last few seconds (plus any missed ones) are awaiting, so this stays small
        return Order::query()
            ->where('status', Order::STATUS_AWAITING_CONFIRMATION)
            ->get(['id', 'status', 'created_at', 'cancel_window_seconds'])
            ->filter(fn (Order $order) => $order->isDueForConfirmation())
            ->sum(fn (Order $order) => $this->confirm($order->id) ? 1 : 0);
    }

    /**
     * Confirms one order if it's due. The conditional update makes sure only one caller wins,
     * so each order is confirmed and notified exactly once even when requests race.
     */
    public function confirm(int $orderId): bool
    {
        $order = Order::find($orderId);

        if (! $order || ! $order->isDueForConfirmation()) {
            return false;
        }

        $updated = Order::query()
            ->whereKey($orderId)
            ->where('status', Order::STATUS_AWAITING_CONFIRMATION)
            ->update([
                'status'       => Order::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'updated_at'   => now(),
            ]);

        if ($updated !== 1) {
            return false;
        }

        $this->notifyConfirmed($order->fresh());

        return true;
    }

    private function notifyConfirmed(Order $order): void
    {
        try {
            $recipients = User::query()->where('user_type', 'admin')->get()
                ->merge($order->farmers()->get())
                ->unique('id');

            Notification::send($recipients, new OrderConfirmed($order));
        } catch (Throwable $e) {
            // The order is confirmed either way; a failed notification must not break the request
            report($e);
        }
    }

    /**
     * Places an order for a buyer. Prices, delivery distance and fee are computed here; the app's values are ignored.
     *
     * @param  array{items: array<int, array{product_id: int|string, quantity: int|string, quality: int|string}>, address?: ?string, city?: ?string, phone?: ?string, delivery_method: string, latitude?: float|string|null, longitude?: float|string|null}  $data
     *
     * @throws ConflictException when stock ran out (code "stock_changed"); nothing is saved
     * @throws ValidationException when no delivery tier covers the buyer's distance
     */
    public function place(User $buyer, array $data): Order
    {
        $this->confirmDueOrders();

        // Merge repeated product/quality pairs into one line
        $lines = [];
        foreach ($data['items'] as $item) {
            $key = (int) $item['product_id'] . ':' . (int) $item['quality'];
            $lines[$key] ??= ['product_id' => (int) $item['product_id'], 'quality' => (int) $item['quality'], 'quantity' => 0];
            $lines[$key]['quantity'] += (int) $item['quantity'];
        }

        $isDelivery = $data['delivery_method'] === 'delivery';
        $deliveryFee = 0;
        $distanceKm = null;

        if ($isDelivery) {
            $distanceKm = $this->delivery->distanceFromWarehouseKm((float) $data['latitude'], (float) $data['longitude']);
            $deliveryFee = $distanceKm === null ? null : $this->delivery->feeForDistance($distanceKm);

            if ($deliveryFee === null) {
                $message = $distanceKm === null
                    ? 'Delivery is not available right now.'
                    : sprintf('Delivery is not available at %s km from the warehouse.', round($distanceKm, 1));

                throw ValidationException::withMessages(['delivery_method' => $message]);
            }
        }

        $order = DB::transaction(function () use ($buyer, $data, $lines, $isDelivery, $deliveryFee, $distanceKm) {
            $productIds = collect($lines)->pluck('product_id')->unique()->sort()->values();

            // Lock the products (in id order, so concurrent checkouts can't deadlock). Every checkout takes
            // these locks before reading held stock, so two orders can't both take the same last kilos.
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $available = $this->stock->availableFor($productIds);

            $short = [];
            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);
                $canOrder = $product && $product->status === 'active'
                    ? $available[$line['product_id']][$line['quality']]
                    : 0.0;

                if ($line['quantity'] > $canOrder) {
                    $short[] = [
                        'product_id' => $line['product_id'],
                        'quality'    => $line['quality'],
                        'title'      => $product?->title,
                        'requested'  => $line['quantity'],
                        'available'  => (int) floor($canOrder),
                    ];
                }
            }

            if ($short) {
                throw new ConflictException(
                    'stock_changed',
                    'Some items are no longer available in that quantity.',
                    ['items' => $short],
                );
            }

            $subtotal = 0.0;
            foreach ($lines as &$line) {
                $line['price'] = (float) $products[$line['product_id']]->priceFor($line['quality']);
                $subtotal += $line['price'] * $line['quantity'];
            }
            unset($line);

            $order = Order::create([
                'buyer_id'              => $buyer->id,
                'total_amount'          => Num::money($subtotal + $deliveryFee),
                'status'                => Order::STATUS_AWAITING_CONFIRMATION,
                'address'               => $data['address'] ?? null,
                'city'                  => $data['city'] ?? null,
                'phone'                 => ($data['phone'] ?? null) ?: $buyer->phone,
                'delivery_method'       => $data['delivery_method'],
                'delivery_fee'          => $deliveryFee,
                'latitude'              => $isDelivery ? (float) $data['latitude'] : null,
                'longitude'             => $isDelivery ? (float) $data['longitude'] : null,
                'distance_km'           => $distanceKm === null ? null : round($distanceKm, 2),
                'cancel_window_seconds' => config('orders.cancel_window_seconds'),
                'confirmed_at'          => null,
                'cancelled_at'          => null,
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $line['product_id'],
                    'quality'    => $line['quality'],
                    'quantity'   => $line['quantity'],
                    'price'      => $line['price'],
                ]);
            }

            // The ordered lines leave the basket
            BasketItem::query()
                ->where('buyer_id', $buyer->id)
                ->where(function ($q) use ($lines) {
                    foreach ($lines as $line) {
                        $q->orWhere(fn ($q) => $q
                            ->where('product_id', $line['product_id'])
                            ->where('quality', $line['quality']));
                    }
                })
                ->delete();

            return $order;
        });

        // Admin and farmers are only told once the buyer can no longer cancel. The check on read
        // confirms the order anyway, so a queue problem must not fail an order that's already saved.
        try {
            ConfirmOrder::dispatch($order->id)
                ->delay(now()->addSeconds($order->cancel_window_seconds + 1));
        } catch (Throwable $e) {
            report($e);
        }

        return $order;
    }

    /**
     * Cancels a buyer's order inside its cancel window: releases its stock and puts its items back in the basket.
     *
     * @throws ConflictException when the window has closed (code "cancel_window_closed")
     */
    public function cancel(Order $order, ?string $reason): Order
    {
        // An order past its window becomes confirmed first, so it's reported as closed below
        $this->confirm($order->id);
        $order->refresh();

        if ($order->status === Order::STATUS_CANCELLED) {
            return $order;
        }

        $closed = new ConflictException('cancel_window_closed', 'This order can no longer be cancelled.');

        if (! $order->isCancellable()) {
            throw $closed;
        }

        return DB::transaction(function () use ($order, $reason, $closed) {
            // Conditional, so a confirmation racing this cancel can't both succeed.
            // Stock is released by the status change itself: cancelled orders no longer hold stock.
            $updated = Order::query()
                ->whereKey($order->id)
                ->where('status', Order::STATUS_AWAITING_CONFIRMATION)
                ->update([
                    'status'        => Order::STATUS_CANCELLED,
                    'cancel_reason' => $reason,
                    'cancelled_at'  => now(),
                    'updated_at'    => now(),
                ]);

            if ($updated !== 1) {
                throw $closed;
            }

            foreach ($order->items as $item) {
                $this->basket->add($order->buyer_id, $item->product_id, $item->quality, $item->quantity);
            }

            return $order->refresh();
        });
    }
}
