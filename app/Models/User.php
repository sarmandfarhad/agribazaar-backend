<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'email',
        'password',
        'user_type',
        'status',
        'token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'token',
    ];

    public function farmer()
    {
        return $this->hasOne(Farmer::class);
    }

    public function buyer()
    {
        return $this->hasOne(Buyer::class);
    }


    public function profile()
    {
        return match ($this->user_type) {
            'farmer' => $this->farmer,
            'buyer'  => $this->buyer,
            default  => null,
        };
    }
    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }
    public function isFarmer(): bool
    {
        return $this->user_type === 'farmer';
    }
    public function isBuyer(): bool
    {
        return $this->user_type === 'buyer';
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function assignedOrders()
    {
        return $this->belongsToMany(Order::class, 'farmer_order', 'farmer_id', 'order_id')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    public function wishlist()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Scope a query to search users by phone, email, or name.
     */
    public function scopeSearch($query, $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('phone', 'ILIKE', '%' . $search . '%')
              ->orWhere('email', 'ILIKE', '%' . $search . '%')
              ->orWhereHas('farmer', function($sub) use ($search) {
                  $sub->where('first_name', 'ILIKE', '%' . $search . '%')
                      ->orWhere('second_name', 'ILIKE', '%' . $search . '%');
              })
              ->orWhereHas('buyer', function($sub) use ($search) {
                  $sub->where('first_name', 'ILIKE', '%' . $search . '%')
                      ->orWhere('second_name', 'ILIKE', '%' . $search . '%')
                      ->orWhere('business_name', 'ILIKE', '%' . $search . '%');
              });
        });
    }
}
