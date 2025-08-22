<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionUsage extends Model
{
    /** @use HasFactory<\Database\Factories\PromotionUsageFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'user_id',
        'order_id',
        'appointment_id',
        'discount_applied',
    ];

    protected $casts = [
        'discount_applied' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
