<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    /** @use HasFactory<\Database\Factories\PromotionFactory> */
    use HasFactory;

    protected $fillable = [
        'business_profile_id',
        'title',
        'description',
        'promotion_type',
        'discount_percentage',
        'discount_amount',
        'buy_quantity',
        'get_quantity',
        'minimum_purchase',
        'maximum_discount',
        'usage_limit',
        'usage_count',
        'user_usage_limit',
        'target_type',
        'target_items',
        'applicable_locations',
        'start_date',
        'end_date',
        'promo_code',
        'is_active',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'minimum_purchase' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'target_items' => 'array',
        'applicable_locations' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function usage(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    public function isValid(): bool
    {
        return $this->is_active && 
               $this->start_date->isPast() && 
               $this->end_date->isFuture() && 
               ($this->usage_limit === null || $this->usage_count < $this->usage_limit);
    }

    public function canUserUse(User $user): bool
    {
        $userUsageCount = $this->usage()->where('user_id', $user->id)->count();
        return $userUsageCount < $this->user_usage_limit;
    }
}
