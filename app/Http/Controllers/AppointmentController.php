<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceStaff;
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

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'No business profile found',
            ], 404);
        }

        $query = Appointment::where('business_profile_id', $businessProfile->id)
            ->with(['customer', 'service', 'staff', 'businessLocation']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->forDate($request->date);
        }

        $appointments = $query->orderBy('appointment_date')
            ->orderBy('start_time')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => AppointmentResource::collection($appointments),
        ], 200);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $service = Service::findOrFail($request->service_id);
            $startTime = $request->start_time;
            $durationMinutes = $service->duration_minutes ?? 60;
            $endTime = date('H:i', strtotime($startTime) + ($durationMinutes * 60));

            if ($request->filled('staff_id')) {
                $staff = ServiceStaff::findOrFail($request->staff_id);
                
                if (!$staff->isAvailable($request->appointment_date, $startTime, $endTime)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected staff is not available at this time',
                    ], 422);
                }
            }

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

            return response()->json([
                'success' => true,
                'message' => 'Appointment booked successfully',
                'data' => new AppointmentResource($appointment->load(['businessProfile', 'service', 'staff'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to book appointment: ' . $e->getMessage(),
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

        if (!$appointment->canBeCancelled()) {
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

        if (!$businessProfile || $appointment->business_profile_id !== $businessProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $appointment->update(['status' => 'confirmed']);

        return response()->json([
            'success' => true,
            'message' => 'Appointment confirmed successfully',
            'data' => new AppointmentResource($appointment->load(['businessProfile' ,'service','staff'])),
        ], 200);
    }
}