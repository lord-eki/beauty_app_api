<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessProfile extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id','business_name','business_type','description','website','instagram','facebook','whatsapp',
        'business_hours','average_rating','total_reviews','is_verified','verification_documents'
    ];

    protected $casts = [
        'business_hours' => 'json',
        'average_rating' => 'decimal',
        'total_reviews' => 'integer',
        'is_verified' => 'boolean',
        'verification_documents' => 'json'
    ];


    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(BusinessLocation::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ServiceStaff::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('business_type', $type);
    }

    // Helper methods
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->exists();
    }

    public function updateRating()
    {
        $this->average_rating = $this->reviews()->avg('rating');
        $this->total_reviews = $this->reviews()->count();
        $this->save();
    }

    
}
