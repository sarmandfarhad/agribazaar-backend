<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmerProduct extends Model
{
    protected $fillable = [
        'farmer_id',
        'product_id',
        'quantity',
        'rating',
    ];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
