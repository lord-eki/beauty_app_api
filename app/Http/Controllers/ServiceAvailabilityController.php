<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\BusinessProfile;
use App\Models\ServiceAvailability;
use App\Http\Resources\ServiceAvailabilityResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceAvailabilityController extends Controller
{
    /**
     * Get the full weekly schedule for the authenticated business
     * GET /api/business/schedule
     */
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'No business profile found.'], 404);
        }

        $schedule = ServiceAvailability::where('business_profile_id', $profile->id)
            ->with(['businessLocation:id,name,city', 'staff:id,name'])
            ->when($request->filled('location_id'), fn($q) => $q->where('business_location_id', $request->location_id))
            ->when($request->filled('staff_id'),    fn($q) => $q->where('staff_id', $request->staff_id))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day_of_week for easy frontend rendering
        $grouped = $schedule->groupBy('day_of_week')
            ->map(fn($slots) => ServiceAvailabilityResource::collection($slots));

        return response()->json([
            'success' => true,
            'data'    => $grouped,
        ]);
    }

    /**
     * Bulk-set schedule slots (replaces existing for same scope)
     * POST /api/business/schedule
     *
     * Send replace_location_id / replace_staff_id to scope what gets cleared.
     * Omit both to replace the entire business schedule.
     */
    public function store(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'No business profile found.'], 404);
        }

        $data = $request->validate([
            'slots'                        => 'required|array|min:1',
            'slots.*.day_of_week'          => 'required|integer|between:0,6',
            'slots.*.start_time'           => 'required|date_format:H:i',
            'slots.*.end_time'             => 'required|date_format:H:i|after:slots.*.start_time',
            'slots.*.is_available'         => 'nullable|boolean',
            'slots.*.business_location_id' => 'nullable|integer|exists:business_locations,id',
            'slots.*.staff_id'             => 'nullable|integer|exists:service_staff,id',
            'replace_location_id'          => 'nullable|integer|exists:business_locations,id',
            'replace_staff_id'             => 'nullable|integer|exists:service_staff,id',
        ]);

        return DB::transaction(function () use ($profile, $data): JsonResponse {

            $deleteQuery = ServiceAvailability::where('business_profile_id', $profile->id);

            if (isset($data['replace_location_id'])) {
                $deleteQuery->where('business_location_id', $data['replace_location_id']);
            }
            if (isset($data['replace_staff_id'])) {
                $deleteQuery->where('staff_id', $data['replace_staff_id']);
            }

            $deleteQuery->delete();

            $created = collect($data['slots'])->map(fn($slot) =>
                ServiceAvailability::create([
                    'business_profile_id'  => $profile->id,
                    'business_location_id' => $slot['business_location_id'] ?? null,
                    'staff_id'             => $slot['staff_id'] ?? null,
                    'day_of_week'          => $slot['day_of_week'],
                    'start_time'           => $slot['start_time'],
                    'end_time'             => $slot['end_time'],
                    'is_available'         => $slot['is_available'] ?? true,
                ])
            );

            return response()->json([
                'success' => true,
                'message' => 'Schedule saved successfully.',
                'data'    => ServiceAvailabilityResource::collection($created),
            ], 201);
        });
    }

    /**
     * Update a single slot (e.g. toggle holiday closure)
     * PUT /api/business/schedule/{availability}
     */
    public function update(Request $request, ServiceAvailability $availability): JsonResponse
    {
        $this->authorizeSlot($request, $availability);

        $data = $request->validate([
            'day_of_week'  => 'sometimes|required|integer|between:0,6',
            'start_time'   => 'sometimes|required|date_format:H:i',
            'end_time'     => 'sometimes|required|date_format:H:i',
            'is_available' => 'sometimes|required|boolean',
        ]);

        $availability->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Slot updated.',
            'data'    => new ServiceAvailabilityResource($availability->fresh()),
        ]);
    }

    /**
     * Remove a single slot
     * DELETE /api/business/schedule/{availability}
     */
    public function destroy(Request $request, ServiceAvailability $availability): JsonResponse
    {
        $this->authorizeSlot($request, $availability);

        $availability->delete();

        return response()->json([
            'success' => true,
            'message' => 'Slot removed.',
        ]);
    }

    /**
     * PUBLIC: Get available time slots for a given date
     * GET /api/business/{businessProfile}/availability?date=2025-07-20&service_id=&staff_id=&location_id=
     *
     * Returns schedule windows with already-booked intervals subtracted,
     * so the Flutter app can render a real-time availability calendar.
     */
    public function publicSlots(Request $request, BusinessProfile $businessProfile): JsonResponse
    {
        $request->validate([
            'date'        => 'required|date|after_or_equal:today',
            'service_id'  => 'nullable|integer|exists:services,id',
            'staff_id'    => 'nullable|integer|exists:service_staff,id',
            'location_id' => 'nullable|integer|exists:business_locations,id',
        ]);

        $date      = Carbon::parse($request->date);
        $dayOfWeek = $date->dayOfWeek; // 0 = Sunday … 6 = Saturday

        $slots = ServiceAvailability::where('business_profile_id', $businessProfile->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->when($request->filled('location_id'), fn($q) => $q->where('business_location_id', $request->location_id))
            ->when($request->filled('staff_id'),    fn($q) => $q->where('staff_id', $request->staff_id))
            ->get();

        if ($slots->isEmpty()) {
            return response()->json([
                'success' => true,
                'data'    => [],
                'message' => 'No availability on this day.',
            ]);
        }

        // Fetch existing confirmed/pending appointments so we can show booked intervals
        $booked = Appointment::where('business_profile_id', $businessProfile->id)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->when($request->filled('staff_id'),    fn($q) => $q->where('staff_id', $request->staff_id))
            ->when($request->filled('location_id'), fn($q) => $q->where('business_location_id', $request->location_id))
            ->get(['start_time', 'end_time', 'staff_id']);

        $available = $slots->map(fn($slot) => [
            'start_time'      => $slot->start_time,
            'end_time'        => $slot->end_time,
            'staff_id'        => $slot->staff_id,
            'location_id'     => $slot->business_location_id,
            'booked_intervals' => $booked
                ->filter(fn($b) => $b->start_time >= $slot->start_time && $b->end_time <= $slot->end_time)
                ->map(fn($b) => ['start' => $b->start_time, 'end' => $b->end_time])
                ->values(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'date'  => $date->toDateString(),
                'day'   => $date->format('l'),
                'slots' => $available,
            ],
        ]);
    }

    // -------------------------------------------------------------------------

    private function authorizeSlot(Request $request, ServiceAvailability $availability): void
    {
        $profile = $request->user()->businessProfile;
        abort_unless(
            $profile && $availability->business_profile_id === $profile->id,
            403,
            'Unauthorized.'
        );
    }
}