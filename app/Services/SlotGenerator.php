<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BusinessProfile;
use App\Models\ServiceAvailability;
use App\Models\ServiceStaff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a business's weekly opening windows (service_availabilities) into
 * concrete bookable time slots for one date.
 *
 *  - slots are generated every STEP minutes inside each opening window
 *  - a slot is as long as the service (duration) and must fit in the window
 *  - slots that clash with an existing booking are returned as unavailable
 *  - times that have already passed today are left out
 */
class SlotGenerator
{
    public const STEP_MINUTES = 30;
    public const TZ = 'Africa/Nairobi';

    /** @return Collection<int, array{start_time:string,end_time:string,is_available:bool,staff_id:?int,staff_name:?string,location_id:?int}> */
    public static function forDay(
        BusinessProfile $business,
        Carbon $date,
        int $durationMinutes,
        ?int $staffId = null,
        ?int $locationId = null,
    ): Collection {
        $day      = $date->copy()->setTimezone(self::TZ)->startOfDay();
        $dateStr  = $day->toDateString();
        $now      = Carbon::now(self::TZ);
        $duration = max($durationMinutes, 15);

        // Opening windows for that weekday (0 = Sunday … 6 = Saturday)
        $windows = ServiceAvailability::where('business_profile_id', $business->id)
            ->where('day_of_week', $day->dayOfWeek)
            ->where('is_available', true)
            ->when($staffId, fn ($q) => $q->where(fn ($w) => $w->where('staff_id', $staffId)->orWhereNull('staff_id')))
            ->when($locationId, fn ($q) => $q->where(fn ($w) => $w->where('business_location_id', $locationId)->orWhereNull('business_location_id')))
            ->orderBy('start_time')
            ->get();

        if ($windows->isEmpty()) {
            return collect();
        }

        // Bookings that block time on that date
        $booked = Appointment::where('business_profile_id', $business->id)
            ->whereDate('appointment_date', $dateStr)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->get(['start_time', 'end_time', 'staff_id']);

        $staffNames = ServiceStaff::whereIn('id', $windows->pluck('staff_id')->filter()->unique())
            ->pluck('name', 'id');

        $slots = collect();
        foreach ($windows as $w) {
            $windowStart = Carbon::parse("$dateStr {$w->start_time}", self::TZ);
            $windowEnd   = Carbon::parse("$dateStr {$w->end_time}", self::TZ);

            for ($s = $windowStart->copy(); $s->copy()->addMinutes($duration)->lte($windowEnd); $s->addMinutes(self::STEP_MINUTES)) {
                $e = $s->copy()->addMinutes($duration);

                // Already in the past (leave 30 min notice)
                if ($s->lt($now->copy()->addMinutes(30))) {
                    continue;
                }

                $clash = $booked->contains(function ($b) use ($s, $e, $dateStr, $w) {
                    $bs = Carbon::parse("$dateStr {$b->start_time}", self::TZ);
                    $be = Carbon::parse("$dateStr {$b->end_time}", self::TZ);
                    $sameResource = ($b->staff_id === null && $w->staff_id === null)
                        || ((int) $b->staff_id === (int) $w->staff_id);
                    return $sameResource && $s->lt($be) && $e->gt($bs);
                });

                $slots->push([
                    'start_time'   => $s->format('H:i'),
                    'end_time'     => $e->format('H:i'),
                    'is_available' => ! $clash,
                    'staff_id'     => $w->staff_id,
                    'staff_name'   => $w->staff_id ? ($staffNames[$w->staff_id] ?? null) : null,
                    'location_id'  => $w->business_location_id,
                ]);
            }
        }

        return $slots->sortBy('start_time')->values();
    }
}