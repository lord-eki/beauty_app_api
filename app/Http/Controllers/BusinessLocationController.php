<?php

namespace App\Http\Controllers;

use App\Models\BusinessLocation;
use App\Models\BusinessProfile;
use App\Models\ServiceStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessLocationController extends Controller
{
    // =========================================================================
    // BUSINESS — manage their own locations
    // =========================================================================

    /**
     * GET /api/business/locations
     */
    public function index(Request $request): JsonResponse
    {
        $business = $this->getAuthBusiness($request);

        $locations = BusinessLocation::where('business_profile_id', $business->id)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $locations]);
    }

    /**
     * POST /api/business/locations
     */
    public function store(Request $request): JsonResponse
    {
        $business = $this->getAuthBusiness($request);

        $data = $request->validate($this->validationRules());

        return DB::transaction(function () use ($business, $data): JsonResponse {

            // If this location is primary, demote any existing primary
            if (!empty($data['is_primary'])) {
                BusinessLocation::where('business_profile_id', $business->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            // If this is the business's first location, auto-make primary
            $isFirst = !BusinessLocation::where('business_profile_id', $business->id)->exists();

            $location = BusinessLocation::create(array_merge(
                $data,
                [
                    'business_profile_id' => $business->id,
                    'is_primary'          => $data['is_primary'] ?? $isFirst,
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Location added successfully.',
                'data'    => $location,
            ], 201);
        });
    }

    /**
     * GET /api/business/locations/{businessLocation}
     */
    public function show(Request $request, BusinessLocation $businessLocation): JsonResponse
    {
        $this->authorizeLocation($request, $businessLocation);

        return response()->json(['success' => true, 'data' => $businessLocation]);
    }

    /**
     * PUT /api/business/locations/{businessLocation}
     */
    public function update(Request $request, BusinessLocation $businessLocation): JsonResponse
    {
        $this->authorizeLocation($request, $businessLocation);

        $data = $request->validate($this->validationRules(update: true));

        return DB::transaction(function () use ($businessLocation, $data): JsonResponse {

            // Promote to primary — demote others first
            if (!empty($data['is_primary'])) {
                BusinessLocation::where('business_profile_id', $businessLocation->business_profile_id)
                    ->where('id', '!=', $businessLocation->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $businessLocation->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully.',
                'data'    => $businessLocation->fresh(),
            ]);
        });
    }

    /**
     * DELETE /api/business/locations/{businessLocation}
     */
    public function destroy(Request $request, BusinessLocation $businessLocation): JsonResponse
    {
        $this->authorizeLocation($request, $businessLocation);

        $business = $this->getAuthBusiness($request);

        // Prevent deleting the only location
        $count = BusinessLocation::where('business_profile_id', $business->id)->count();
        if ($count <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the only location. Update it instead.',
            ], 422);
        }

        return DB::transaction(function () use ($businessLocation, $business): JsonResponse {

            $wasPrimary = $businessLocation->is_primary;
            $businessLocation->delete();

            // Auto-promote first remaining location if primary was deleted
            if ($wasPrimary) {
                BusinessLocation::where('business_profile_id', $business->id)
                    ->orderBy('id')
                    ->first()
                    ?->update(['is_primary' => true]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Location removed.',
            ]);
        });
    }

    // =========================================================================
    // Location-specific sub-resources (read-only, business-scoped)
    // =========================================================================

    /**
     * GET /api/business/locations/{businessLocation}/inventory
     * Inventory stock levels at this specific location.
     */
    public function inventory(Request $request, BusinessLocation $businessLocation): JsonResponse
    {
        $this->authorizeLocation($request, $businessLocation);

        $inventory = $businessLocation->inventory()
            ->with('product:id,name,sku,price,images')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $inventory]);
    }

    /**
     * GET /api/business/locations/{businessLocation}/appointments
     * Appointments scheduled at this location.
     */
    public function appointments(Request $request, BusinessLocation $businessLocation): JsonResponse
    {
        $this->authorizeLocation($request, $businessLocation);

        $request->validate([
            'date'   => 'nullable|date',
            'status' => 'nullable|in:pending,confirmed,in_progress,completed,cancelled,no_show',
        ]);

        $query = $businessLocation->appointments()
            ->with(['customer:id,first_name,last_name,phone', 'service:id,name', 'staff:id,name'])
            ->orderBy('appointment_date')
            ->orderBy('start_time');

        if ($request->filled('date')) {
            $query->whereDate('appointment_date', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    /**
     * GET /api/business/locations/{businessLocation}/staff
     * Staff assigned to (or available at) this location.
     */
    public function staff(Request $request, BusinessLocation $businessLocation): JsonResponse
    {
        $this->authorizeLocation($request, $businessLocation);

        $staff = ServiceStaff::query()
        ->join('service_availabilities','service_staff.id', '=','service_availabilites.service_staff')
        ->where('service_availabilities.business_location_id',$businessLocation->id)
        ->where('service_staff.is_active',true)
        ->select('service_staff.*')
        ->distinct()
        ->get();

        return response()->json(['success' => true, 'data' => $staff]);
    }

    // =========================================================================
    // PUBLIC — nearest locations search
    // =========================================================================

    /**
     * GET /api/locations/nearby?lat=&lng=&radius=&business_id=
     * Find active locations near a coordinate.
     */
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'lat'         => 'required|numeric|between:-90,90',
            'lng'         => 'required|numeric|between:-180,180',
            'radius'      => 'nullable|numeric|min:1|max:200',
            'business_id' => 'nullable|integer|exists:business_profiles,id',
        ]);

        $query = BusinessLocation::active()
            ->nearby(
                (float) $request->lat,
                (float) $request->lng,
                (float) ($request->radius ?? 20)
            )
            ->with('businessProfile:id,business_name,average_rating,is_verified');

        if ($request->filled('business_id')) {
            $query->where('business_profile_id', $request->business_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(15),
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function getAuthBusiness(Request $request): BusinessProfile
    {
        $business = $request->user()->businessProfile;
        abort_unless($business, 403, 'No business profile found.');
        return $business;
    }

    private function authorizeLocation(Request $request, BusinessLocation $location): void
    {
        $business = $this->getAuthBusiness($request);
        abort_unless(
            $location->business_profile_id === $business->id,
            403,
            'You do not own this location.'
        );
    }

    private function validationRules(bool $update = false): array
    {
        $required = $update ? 'sometimes|required' : 'required';

        return [
            'name'        => 'nullable|string|max:255',
            'address'     => "{$required}|string",
            'city'        => "{$required}|string|max:100",
            'county'      => "{$required}|string|max:100",
            'postal_code' => 'nullable|string|max:20',
            'latitude'    => "{$required}|numeric|between:-90,90",
            'longitude'   => "{$required}|numeric|between:-180,180",
            'is_primary'  => 'nullable|boolean',
            'is_active'   => 'nullable|boolean',
        ];
    }
}