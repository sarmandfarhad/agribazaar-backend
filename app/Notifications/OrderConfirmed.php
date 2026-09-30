<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class OrderConfirmed extends Notification
{
    public function __construct(public Order $order)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'order_confirmed',
            'order_id'     => $this->order->id,
            'buyer_id'     => $this->order->buyer_id,
            'total_amount' => $this->order->total_amount,
            'message'      => "Order #{$this->order->id} was confirmed.",
        ];
    }
}
