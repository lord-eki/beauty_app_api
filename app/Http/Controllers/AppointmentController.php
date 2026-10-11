<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceStaff;
use App\Models\BusinessProfile;
use App\Services\CacheService;
use App\Services\SlotGenerator;
use Carbon\Carbon;
use App\Services\PaymentRecorder;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Appointment::where('customer_id', $request->user()->id)
            ->with(['businessProfile', 'service', 'staff', 'businessLocation']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('filter')) {
            if ($request->filter === 'upcoming') {
                $query->upcoming();
            } elseif ($request->filter === 'past') {
                $query->past();
            }
        }

        $appointments = $query->orderBy('appointment_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => AppointmentResource::collection($appointments),
        ], 200);
    }

    public function businessAppointments(Request $request): JsonResponse
    {
        $businessProfile = $request->user()->businessProfile;
        if (! $businessProfile) {
            return response()->json(['success' => false, 'message' => 'No business profile found'], 404);
        }

        $query = Appointment::where('business_profile_id', $businessProfile->id)
            ->with([
                'customer:id,first_name,last_name,phone,profile_image',
                'service:id,name,duration_minutes,price_min',
                'staff:id,name,phone',
                'businessLocation:id,name,city,address',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date')) {
            $query->whereDate('appointment_date', $request->date);
        }

        return response()->json([
            'success' => true,
            'data' => AppointmentResource::collection($query->paginate(20)),
        ]);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $service         = Service::findOrFail($request->service_id);
        $startTime       = $request->start_time;
        $durationMinutes = $service->duration_minutes ?? 60;
        $endTime         = date('H:i', strtotime($startTime) + ($durationMinutes * 60));

        // The chosen time must be a real, free slot (inside opening hours and
        // not clashing with another booking).
        $slots = SlotGenerator::forDay(
            BusinessProfile::findOrFail($service->business_profile_id),
            Carbon::parse($request->appointment_date),
            (int) $durationMinutes,
            $request->filled('staff_id') ? (int) $request->staff_id : null,
            $request->filled('business_location_id') ? (int) $request->business_location_id : null,
        );

        $free = $slots->first(fn ($s) => $s['start_time'] === date('H:i', strtotime($startTime)) && $s['is_available']);

        if (! $free) {
            return response()->json([
                'success' => false,
                'message' => 'That time is no longer available. Please pick another slot.',
            ], 422);
        }

        DB::beginTransaction();

        try {

            $appointment = Appointment::create([
                'customer_id' => $request->user()->id,
                'business_profile_id' => $service->business_profile_id,
                'business_location_id' => $request->business_location_id,
                'service_id' => $request->service_id,
                'staff_id' => $request->staff_id,
                'appointment_date' => $request->appointment_date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'pending',
                'customer_notes' => $request->customer_notes,
                'total_amount' => $service->price_min ?? 0,
                'payment_status' => 'pending',
            ]);

            DB::commit();

            CacheService::forgetAvailability(
                $appointment->business_profile_id,
                $appointment->appointment_date
            );
            CacheService::forgetBusinessStats($appointment->business_profile_id);

            return response()->json([
                'success' => true,
                'message' => 'Appointment booked successfully',
                'data' => new AppointmentResource($appointment->load(['businessProfile', 'service', 'staff'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to book appointment: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->customer_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (! $appointment->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellations must be made at least 24 hours in advance.',
            ], 422);
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('reason', 'Cancelled by customer'),
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Appointment cancelled successfully',
        ], 200);
    }

    public function confirm(Request $request, Appointment $appointment): JsonResponse
    {
        $businessProfile = $request->user()->businessProfile;

        if (! $businessProfile || $appointment->business_profile_id !== $businessProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $appointment->update(['status' => 'confirmed']);

        return response()->json([
            'success' => true,
            'message' => 'Appointment confirmed successfully',
            'data' => new AppointmentResource($appointment->load(['businessProfile', 'service', 'staff'])),
        ], 200);
    }

    private const RELATIONS = ['businessProfile', 'service', 'staff', 'customer:id,first_name,last_name,phone', 'payments'];

    /** The business that owns this appointment, if the user is that business. */
    private function ownerProfile(Request $request, Appointment $appointment)
    {
        $profile = $request->user()->businessProfile;
        return ($profile && $appointment->business_profile_id === $profile->id) ? $profile : null;
    }

    /**
     * GET /api/appointments/{appointment}  (the customer or the business)
     */
    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->customer_id !== $request->user()->id && ! $this->ownerProfile($request, $appointment)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new AppointmentResource($appointment->load(self::RELATIONS)),
        ], 200);
    }

    /**
     * PUT /api/appointments/{appointment}
     * - business: change status (+ business notes)
     * - customer: edit their own notes
     */
    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        if ($this->ownerProfile($request, $appointment)) {
            $data = $request->validate([
                'status'         => ['sometimes', Rule::in(['confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])],
                'business_notes' => ['nullable', 'string', 'max:1000'],
            ]);

            if (($data['status'] ?? null) === 'cancelled') {
                $data['cancelled_at'] = now();
                $data['cancelled_by'] = $request->user()->id;
            }

            $appointment->update($data);
        } elseif ($appointment->customer_id === $request->user()->id) {
            $data = $request->validate(['customer_notes' => ['nullable', 'string', 'max:1000']]);
            $appointment->update($data);
        } else {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        CacheService::forgetSearchResults();

        return response()->json([
            'success' => true,
            'message' => 'Appointment updated successfully',
            'data' => new AppointmentResource($appointment->fresh(self::RELATIONS)),
        ], 200);
    }

    /**
     * POST /api/business/appointments/{appointment}/complete
     */
    public function complete(Request $request, Appointment $appointment): JsonResponse
    {
        if (! $this->ownerProfile($request, $appointment)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (! in_array($appointment->status, ['confirmed', 'in_progress'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only confirmed appointments can be completed.',
            ], 422);
        }

        $appointment->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'Appointment completed',
            'data' => new AppointmentResource($appointment->fresh(self::RELATIONS)),
        ], 200);
    }

    /**
     * Record that the customer paid the business (after the service).
     * POST /api/business/appointments/{appointment}/payment
     */
    public function recordPayment(Request $request, Appointment $appointment): JsonResponse
    {
        $profile = $this->ownerProfile($request, $appointment);
        if (! $profile) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($appointment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Record the payment after the service has been completed.',
            ], 422);
        }

        if ($appointment->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This appointment is already marked as paid.',
            ], 422);
        }

        $data = $request->validate([
            'method'    => ['required', Rule::in(PaymentRecorder::METHODS)],
            'amount'    => ['nullable', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at'   => ['nullable', 'date'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        PaymentRecorder::record($appointment, $profile->id, $data, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded',
            'data' => new AppointmentResource($appointment->fresh(self::RELATIONS)),
        ], 200);
    }
}