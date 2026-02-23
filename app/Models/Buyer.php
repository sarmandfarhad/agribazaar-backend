<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Buyer extends Model
{
    protected $fillable = [
        'user_id', 'first_name', 'second_name', 'address', 'city', 'business_name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
