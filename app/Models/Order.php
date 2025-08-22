<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'business_profile_id',
        'order_number',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'delivery_fee',
        'total_amount',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'delivery_type',
        'delivery_address',
        'pickup_location_id',
        'notes',
        'estimated_delivery',
        'delivered_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_address' => 'array',
        'estimated_delivery' => 'date',
        'delivered_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->order_number)) {
                $model->order_number = 'ORD-' . strtoupper(Str::random(8));
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class, 'pickup_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function promotionUsage(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }
}
