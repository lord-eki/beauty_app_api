<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessLocation extends Model
{
   use HasFactory;

    protected $fillable = [
        'business_profile_id', 'name', 'address', 'city', 'county',
        'postal_code', 'latitude', 'longitude', 'is_primary', 'is_active',
    ];

    protected $casts = [
        'latitude'   => 'decimal:8',
        'longitude'  => 'decimal:8',
        'is_primary' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(ProductInventory::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pickup_location_id');
    }

    // Helper: get total available stock for a product at this location
    public function stockFor(int $productId): int
    {
        return $this->inventory()
            ->where('product_id', $productId)
            ->value('quantity_available') ?? 0;
    }

}
