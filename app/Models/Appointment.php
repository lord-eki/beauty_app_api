<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'business_profile_id',
        'business_location_id',
        'service_id',
        'staff_id',
        'appointment_date',
        'start_time',
        'end_time',
        'status',
        'customer_notes',
        'business_notes',
        'total_amount',
        'payment_status',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'reminder_sent_at',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'total_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function businessLocation(): BelongsTo
    {
        return $this->belongsTo(BusinessLocation::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(ServiceStaff::class, 'staff_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed'])
            ->where('appointment_date', '>=', now()->toDateString());
    }

    public function scopePast($query)
    {
        return $query->where('appointment_date', '<', now()->toDateString());
    }

    public function scopeToday($query)
    {
        return $query->where('appointment_date', now()->toDateString());
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('appointment_date', $date);
    }

    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isUpcoming(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) 
            && $this->appointment_date >= now()->toDateString();
    }

    public function canBeCancelled(): bool
    {
        // Can cancel if pending or confirmed and at least 24 hours before appointment
        if (!in_array($this->status, ['pending', 'confirmed'])) {
            return false;
        }

        $appointmentDateTime = strtotime($this->appointment_date . ' ' . $this->start_time);
        $now = time();
        $hoursUntilAppointment = ($appointmentDateTime - $now) / 3600;
        
        return $hoursUntilAppointment >= 24;
    }

    public function getDurationInMinutes(): int
    {
        $start = strtotime($this->start_time);
        $end = strtotime($this->end_time);
        return ($end - $start) / 60;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}