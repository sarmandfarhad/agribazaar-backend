<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'price',
        'quantity',
        'quality',
    ];

    protected $casts = [
        'order_id'   => 'integer',
        'product_id' => 'integer',
        'price'      => 'decimal:2',
        'quantity'   => 'integer',
        'quality'    => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
