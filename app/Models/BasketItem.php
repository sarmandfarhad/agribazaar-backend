<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BasketItem extends Model
{
    protected $fillable = [
        'buyer_id',
        'product_id',
        'quality',
        'quantity',
    ];

    protected $casts = [
        'buyer_id'   => 'integer',
        'product_id' => 'integer',
        'quality'    => 'integer',
        'quantity'   => 'integer',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
