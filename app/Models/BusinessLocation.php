<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessLocation extends Model
{
    protected $fillable = [
        'business_profile_id',
        'name',
        'address',
        'city',
        'county',
        'postal_code',
        'latitude',
        'longitude',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'latitude'   => 'decimal:8',
        'longitude'  => 'decimal:8',
        'is_primary' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

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

    public function serviceAvailability(): HasMany
    {
        return $this->hasMany(ServiceAvailability::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Order by proximity to a lat/lng point (Haversine formula).
     */
    public function scopeNearby($query, float $lat, float $lng, float $radiusKm = 50)
    {
        $haversine = "(
            6371 * ACOS(
                COS(RADIANS(?)) * COS(RADIANS(latitude)) *
                COS(RADIANS(longitude) - RADIANS(?)) +
                SIN(RADIANS(?)) * SIN(RADIANS(latitude))
            )
        )";

        return $query
            ->selectRaw("*, {$haversine} AS distance_km", [$lat, $lng, $lat])
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km');
    }
}