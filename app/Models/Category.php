<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'isActive'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope a query to search categories by name.
     */
    public function scopeSearch($query, $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where('name', 'ILIKE', '%' . $search . '%');
    }
}
