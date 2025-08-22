<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductInventory extends Model
{
    /** @use HasFactory<\Database\Factories\ProductInventoryFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'business_location_id',
        'quantity_available',
        'quantity_reserved',
        'minimum_stock_level',
        'maximum_stock_level',
        'cost_price',
        'last_restocked_at',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'last_restocked_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function businessLocation(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity_available <= $this->minimum_stock_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity_available <= 0;
    }
}
