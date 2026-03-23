<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFeedback extends Model
{
    protected $table = 'order_feedback';

    protected $fillable = [
        'order_id',
        'user_id',
        'problem_type',
        'priority',
        'description',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
