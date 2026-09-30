<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Confirms an order when its cancel window ends, for when nobody opens the app.
 * Needs a queue worker; on Vercel there is none, so the check-on-read in OrderService does the real work.
 */
class ConfirmOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId)
    {
    }

    public function handle(OrderService $orders): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->status !== Order::STATUS_AWAITING_CONFIRMATION) {
            return;
        }

        if (! $order->isDueForConfirmation()) {
            // Picked up early (or run by the sync driver): try again once the window has ended
            $this->release(max(1, (int) ceil(now()->diffInSeconds($order->cancelDeadline(), false)) + 1));

            return;
        }

        $orders->confirm($order->id);
    }
}
