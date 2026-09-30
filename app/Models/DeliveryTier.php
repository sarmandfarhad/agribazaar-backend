<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTier extends Model
{
    protected $fillable = [
        'min_km',
        'max_km',
        'fee',
    ];

    protected $casts = [
        'min_km' => 'float',
        'max_km' => 'float',
        'fee'    => 'integer',
    ];
}
