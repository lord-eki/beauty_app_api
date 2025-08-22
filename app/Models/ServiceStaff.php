<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceStaff extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceStaffFactory> */
    use HasFactory;

    protected $fillable = [
        'business_profile_id',
        'name',
        'email',
        'phone',
        'specialties',
        'is_active',
    ];

    protected $casts = [
        'specialties' => 'array',
        'is_active' => 'boolean',
    ];

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'staff_id');
    }

    public function availability(): HasMany
    {
        return $this->hasMany(ServiceAvailability::class, 'staff_id');
    }
}
