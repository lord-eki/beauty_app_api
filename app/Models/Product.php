<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_profile_id',
        'category_id',
        'name',
        'description',
        'brand',
        'price',
        'discounted_price',
        'stock_quantity',
        'sku',
        'images',
        'specifications',
        'is_active',
    ];

    protected $casts = [
        'images'           => 'array',
        'specifications'   => 'array',
        'price'            => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'is_active'        => 'boolean',
        'stock_quantity'   => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand($query, $brand)
    {
        return $query->where('brand', $brand);
    }

    public function scopePriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    // ─── Helper Methods ───────────────────────────────────────────────────────

    /**
     * Returns discounted price if available, otherwise regular price
     */
    public function getEffectivePrice(): float
    {
        return (float) ($this->discounted_price ?? $this->price);
    }

    /**
     * Returns true if the product has an active discount
     */
    public function hasDiscount(): bool
    {
        return !is_null($this->discounted_price)
            && (float) $this->discounted_price < (float) $this->price;
    }

    /**
     * Returns the discount percentage (0 if no discount)
     */
    public function getDiscountPercentage(): int
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return (int) round(
            (($this->price - $this->discounted_price) / $this->price) * 100
        );
    }

    /**
     * Returns true if product is in stock
     */
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Returns true if product is available (active + in stock)
     */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->isInStock();
    }
}