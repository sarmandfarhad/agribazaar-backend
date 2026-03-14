<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'buyer_id',
        'total_amount',
        'status',
        'address',
        'city',
        'phone',
        'cancel_reason',
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
}
