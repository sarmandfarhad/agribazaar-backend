<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliverySetting extends Model
{
    protected $fillable = [
        'warehouse_name',
        'warehouse_address',
        'warehouse_latitude',
        'warehouse_longitude',
    ];

    protected $casts = [
        'warehouse_latitude'  => 'float',
        'warehouse_longitude' => 'float',
    ];
}
