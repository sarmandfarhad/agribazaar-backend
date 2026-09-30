<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Order extends Model
{
    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'buyer_id',
        'total_amount',
        'status',
        'address',
        'city',
        'phone',
        'cancel_reason',
        'delivery_method',
        'delivery_fee',
        'latitude',
        'longitude',
        'distance_km',
        'cancel_window_seconds',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'buyer_id'              => 'integer',
        'total_amount'          => 'decimal:2',
        'delivery_fee'          => 'integer',
        'latitude'              => 'float',
        'longitude'             => 'float',
        'distance_km'           => 'float',
        'cancel_window_seconds' => 'integer',
        'confirmed_at'          => 'datetime',
        'cancelled_at'          => 'datetime',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function farmers()
    {
        return $this->belongsToMany(User::class, 'farmer_order', 'order_id', 'farmer_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function feedback()
    {
        return $this->hasMany(OrderFeedback::class);
    }

    /**
     * The last moment the buyer can still cancel.
     */
    public function cancelDeadline(): Carbon
    {
        return $this->created_at->copy()->addSeconds($this->cancel_window_seconds);
    }

    /**
     * Still awaiting confirmation but past its cancel window.
     */
    public function isDueForConfirmation(): bool
    {
        return $this->status === self::STATUS_AWAITING_CONFIRMATION
            && now()->greaterThan($this->cancelDeadline());
    }

    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_AWAITING_CONFIRMATION
            && $this->cancel_window_seconds > 0
            && ! now()->greaterThan($this->cancelDeadline());
    }

    /**
     * Orders a farmer may see: not waiting in the cancel window, and not cancelled inside it.
     * Orders from before the cancel window existed (cancel_window_seconds = 0) keep their old visibility.
     */
    public function scopeVisibleToFarmers(Builder $query): Builder
    {
        return $query
            ->where('orders.status', '!=', self::STATUS_AWAITING_CONFIRMATION)
            ->where(function (Builder $q) {
                $q->where('orders.status', '!=', self::STATUS_CANCELLED)
                    ->orWhere('orders.cancel_window_seconds', 0)
                    ->orWhereNotNull('orders.confirmed_at');
            });
    }
}
