<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Farmer extends Model
{
    protected $fillable = [
        'user_id', 'first_name', 'second_name', 'address', 'city',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function farmerProducts()
    {
        return $this->hasMany(FarmerProduct::class);
    }
}
