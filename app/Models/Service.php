<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServicesFactory> */
    use HasFactory;

    protected $fillable = [
        'business_profile_id',
        'category_id',
        'name',
        'description',
        'price_min',
        'price_max',
        'duration_minutes',
        'images',
        'is_home_service',
        'is_active',
    ];

    protected $casts = [
        'images' => 'array',
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
        'is_home_service' => 'boolean',
        'is_active' => 'boolean',
    ];

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

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
