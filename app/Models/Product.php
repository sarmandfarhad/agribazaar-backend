<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'price_good',
        'price_normal',
        'price_bad',
        'image',
        'quantity',
        'information',
        'total_orders',
        'total_quantity',
        'category_id',
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function farmerProducts()
    {
        return $this->hasMany(FarmerProduct::class);
    }

    public function wishlistedBy()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the URL for the product image.
     *
     * @return string|null
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['image_url', 'good_quantity', 'normal_quantity', 'bad_quantity'];

    /**
     * Get the good quality quantity for this product.
     */
    public function getGoodQuantityAttribute(): float
    {
        if ($this->relationLoaded('farmerProducts')) {
            return (float) $this->farmerProducts->where('rating', 3)->sum('quantity');
        }

        return (float) $this->farmerProducts()->where('rating', 3)->sum('quantity');
    }

    /**
     * Get the normal quality quantity for this product.
     */
    public function getNormalQuantityAttribute(): float
    {
        if ($this->relationLoaded('farmerProducts')) {
            return (float) $this->farmerProducts->where('rating', 2)->sum('quantity');
        }

        return (float) $this->farmerProducts()->where('rating', 2)->sum('quantity');
    }

    /**
     * Get the bad quality quantity for this product.
     */
    public function getBadQuantityAttribute(): float
    {
        if ($this->relationLoaded('farmerProducts')) {
            return (float) $this->farmerProducts->where('rating', 1)->sum('quantity');
        }

        return (float) $this->farmerProducts()->where('rating', 1)->sum('quantity');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_good'   => 'decimal:2',
            'price_normal' => 'decimal:2',
            'price_bad'    => 'decimal:2',
            'quantity'       => 'decimal:2',
            'total_orders'   => 'integer',
            'total_quantity' => 'decimal:2',
        ];
    }

    /**
     * Scope a query to search products by title or information.
     */
    public function scopeSearch($query, $search)
    {
        if (!$search) {
            return $query;
        }

        $search = trim($search);

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'ILIKE', '%' . $search . '%')
                ->orWhere('information', 'ILIKE', '%' . $search . '%')
                ->orWhereHas('category', function ($sub) use ($search) {
                    $sub->where('name', 'ILIKE', '%' . $search . '%');
                });

            // Split by space to search individual words if there are multiple
            $words = array_filter(explode(' ', $search));
            if (count($words) > 1) {
                foreach ($words as $word) {
                    if (strlen($word) > 2) {
                        $q->orWhere('title', 'ILIKE', '%' . $word . '%')
                            ->orWhere('information', 'ILIKE', '%' . $word . '%');
                    }
                }
            }
        });
    }
}
