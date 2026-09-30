<?php

namespace App\Models;

use App\Services\StockService;
use App\Support\MediaUrl;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
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

    /**
     * @var list<string>
     */
    protected $appends = ['image_url', 'price', 'good_quantity', 'normal_quantity', 'bad_quantity'];

    /**
     * @var list<string>
     */
    protected $hidden = ['farmerProducts'];

    /**
     * Available kg per quality ([3 => good, 2 => normal, 1 => bad]), see StockService.
     *
     * @var array<int, float>|null
     */
    protected ?array $availableStock = null;

    protected function casts(): array
    {
        return [
            'price_good'   => 'decimal:2',
            'price_normal' => 'decimal:2',
            'price_bad'    => 'decimal:2',
            'quantity'     => 'decimal:2',
            'total_orders' => 'integer',
        ];
    }

    // ─── Relations ─────────────────────────────────────────────────

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

    // ─── Stock and prices ──────────────────────────────────────────

    public function setAvailableStock(array $byQuality): static
    {
        $this->availableStock = $byQuality;

        return $this;
    }

    /**
     * @return array<int, float>
     */
    public function availableStock(): array
    {
        return $this->availableStock ??= app(StockService::class)->availableFor([$this->id])[$this->id];
    }

    public function availableFor(int $quality): float
    {
        return $this->availableStock()[$quality] ?? 0.0;
    }

    /**
     * Current unit price for a quality (3 = good, 2 = normal, 1 = bad).
     */
    public function priceFor(int $quality): string
    {
        return match ($quality) {
            3 => $this->price_good,
            2 => $this->price_normal,
            1 => $this->price_bad,
        };
    }

    // ─── Accessors ─────────────────────────────────────────────────

    public function getImageUrlAttribute(): ?string
    {
        return MediaUrl::for($this->image);
    }

    /**
     * The app's headline price, the same fallback it uses itself.
     */
    public function getPriceAttribute(): ?string
    {
        return $this->price_good;
    }

    /**
     * Available (not yet ordered) good quality kg.
     */
    public function getGoodQuantityAttribute(): float|int
    {
        return Num::clean($this->availableFor(3));
    }

    /**
     * Available (not yet ordered) normal quality kg.
     */
    public function getNormalQuantityAttribute(): float|int
    {
        return Num::clean($this->availableFor(2));
    }

    /**
     * Available (not yet ordered) bad quality kg.
     */
    public function getBadQuantityAttribute(): float|int
    {
        return Num::clean($this->availableFor(1));
    }

    /**
     * Total available kg across qualities. Overrides the stored column, which only
     * mirrors raw farmer stock and ignores quantities held by orders.
     */
    public function getTotalQuantityAttribute(): float|int
    {
        return Num::clean(array_sum($this->availableStock()));
    }

    // ─── Scopes ────────────────────────────────────────────────────

    /**
     * Search by title, information or category name; with several words, also by each word.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
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
