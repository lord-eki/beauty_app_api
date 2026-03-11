<?php

namespace App\Http\Controllers;

use App\Models\ServiceStaff;
use App\Http\Resources\ServiceStaffResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceStaffController extends Controller
{
    /**
     * List all staff for the authenticated business
     * GET /api/business/staff
     */
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'No business profile found.'], 404);
        }

        $staff = ServiceStaff::where('business_profile_id', $profile->id)
            ->when(
                $request->has('is_active'),
                fn($q) => $q->where('is_active', $request->boolean('is_active'))
            )
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => ServiceStaffResource::collection($staff),
        ]);
    }

    /**
     * Add a new staff member
     * POST /api/business/staff
     */
    public function store(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'No business profile found.'], 404);
        }

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'nullable|email|max:191',
            'phone'         => 'nullable|string|max:20',
            'specialties'   => 'nullable|array',
            'specialties.*' => 'integer|exists:services,id',
            'is_active'     => 'nullable|boolean',
        ]);

        $staff = ServiceStaff::create(array_merge(
            $data,
            ['business_profile_id' => $profile->id]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Staff member added successfully.',
            'data'    => new ServiceStaffResource($staff),
        ], 201);
    }

    /**
     * Get a single staff member
     * GET /api/business/staff/{staff}
     */
    public function show(Request $request, ServiceStaff $staff): JsonResponse
    {
        $this->authorizeStaff($request, $staff);

        return response()->json([
            'success' => true,
            'data'    => new ServiceStaffResource($staff),
        ]);
    }

    /**
     * Update a staff member
     * PUT /api/business/staff/{staff}
     */
    public function update(Request $request, ServiceStaff $staff): JsonResponse
    {
        $this->authorizeStaff($request, $staff);

        $data = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'email'         => 'nullable|email|max:191',
            'phone'         => 'nullable|string|max:20',
            'specialties'   => 'nullable|array',
            'specialties.*' => 'integer|exists:services,id',
            'is_active'     => 'nullable|boolean',
        ]);

        $staff->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Staff member updated successfully.',
            'data'    => new ServiceStaffResource($staff->fresh()),
        ]);
    }

    /**
     * Deactivate a staff member (soft delete to preserve appointment history)
     * DELETE /api/business/staff/{staff}
     */
    public function destroy(Request $request, ServiceStaff $staff): JsonResponse
    {
        $this->authorizeStaff($request, $staff);

        $staff->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Staff member deactivated.',
        ]);
    }

    // -------------------------------------------------------------------------

    private function authorizeStaff(Request $request, ServiceStaff $staff): void
    {
        $profile = $request->user()->businessProfile;
        abort_unless(
            $profile && $staff->business_profile_id === $profile->id,
            403,
            'Unauthorized.'
        );
    }
}