<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Helper methods
    public function calculateTotal(): void
    {
        $this->total_price = ($this->unit_price * $this->quantity) - $this->discount_amount;
        $this->save();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            $item->total_price = ($item->unit_price * $item->quantity) - ($item->discount_amount ?? 0);
        });

        static::updating(function ($item) {
            $item->total_price = ($item->unit_price * $item->quantity) - ($item->discount_amount ?? 0);
        });
    }
}