<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Promotion extends Model
{
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
        'target_items'          => 'array',
        'applicable_locations'  => 'array',
        'start_date'            => 'date',
        'end_date'              => 'date',
        'is_active'             => 'boolean',
        'discount_percentage'   => 'decimal:2',
        'discount_amount'       => 'decimal:2',
        'minimum_purchase'      => 'decimal:2',
        'maximum_discount'      => 'decimal:2',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Only promotions currently active by date and flag. */
    public function scopeActive($query)
    {
        $today = Carbon::today()->toDateString();

        return $query->where('is_active', true)
                     ->where('start_date', '<=', $today)
                     ->where('end_date', '>=', $today);
    }

    /** Promotions belonging to a business. */
    public function scopeForBusiness($query, int $businessProfileId)
    {
        return $query->where('business_profile_id', $businessProfileId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Whether this promotion is currently live (active + within dates). */
    public function isCurrentlyActive(): bool
    {
        $today = Carbon::today();

        return $this->is_active
            && $today->greaterThanOrEqualTo($this->start_date)
            && $today->lessThanOrEqualTo($this->end_date);
    }

    /** Whether the global usage cap has been reached. */
    public function hasReachedGlobalLimit(): bool
    {
        return $this->usage_limit !== null && $this->usage_count >= $this->usage_limit;
    }

    /**
     * How many times a specific user has already redeemed this promotion.
     */
    public function usageCountForUser(int $userId): int
    {
        return $this->usages()->where('user_id', $userId)->count();
    }

    /**
     * Whether a user is still eligible (under per-user cap).
     */
    public function isEligibleForUser(int $userId): bool
    {
        return $this->usageCountForUser($userId) < $this->user_usage_limit;
    }

    /**
     * Calculate the discount amount for a given subtotal.
     *
     * @param  float $subtotal   Cart/appointment subtotal in KES
     * @return float             Discount to deduct
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->minimum_purchase && $subtotal < $this->minimum_purchase) {
            return 0.0;
        }

        $discount = match ($this->promotion_type) {
            'percentage'          => $subtotal * ($this->discount_percentage / 100),
            'fixed_amount'        => (float) $this->discount_amount,
            'free_service'        => (float) $this->discount_amount,   // set by admin
            'buy_x_get_y'         => $this->calcBuyXGetY($subtotal),
            'bundle_deal'         => (float) $this->discount_amount,
            'first_time_customer' => $this->discount_percentage
                                        ? $subtotal * ($this->discount_percentage / 100)
                                        : (float) $this->discount_amount,
            'loyalty_reward'      => (float) $this->discount_amount,
            default               => 0.0,
        };

        // Cap at maximum_discount if set
        if ($this->maximum_discount !== null) {
            $discount = min($discount, (float) $this->maximum_discount);
        }

        // Never discount more than the subtotal
        return min(round($discount, 2), $subtotal);
    }

    private function calcBuyXGetY(float $subtotal): float
    {
        // Simplified: give discount equivalent to get_quantity items proportionally
        if (!$this->buy_quantity || !$this->get_quantity) {
            return 0.0;
        }

        $unitPrice = $subtotal / ($this->buy_quantity + $this->get_quantity);

        return $unitPrice * $this->get_quantity;
    }
}