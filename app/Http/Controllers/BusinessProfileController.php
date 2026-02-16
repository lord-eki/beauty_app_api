<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessProfileRequest;
use App\Http\Requests\UpdateBusinessProfileRequest;
use App\Http\Resources\BusinessProfileResource;
use App\Models\BusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class BusinessProfileController extends Controller
{
    /**
     * Get authenticated provider's business profile
     * GET /api/business/profile
     */
    public function show(Request $request): JsonResponse
    {
        // Ensure user is a provider
        if (!$request->user()->isProvider()) {
            return response()->json([
                'success' => false,
                'message' => 'Only providers can access business profiles',
            ], 403);
        }

        $businessProfile = $request->user()->businessProfile;

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found. Please create one first.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new BusinessProfileResource($businessProfile),
        ], 200);
    }

    /**
     * Create a new business profile
     * POST /api/business/profile
     */
    public function store(StoreBusinessProfileRequest $request): JsonResponse
    {
        // Ensure user is a provider
        if (!$request->user()->isProvider()) {
            return response()->json([
                'success' => false,
                'message' => 'Only providers can create business profiles',
            ], 403);
        }

        // Check if user already has a business profile
        if ($request->user()->businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a business profile. Use update instead.',
            ], 422);
        }

        // Create business profile
        $businessProfile = $request->user()->businessProfile()->create([
            'business_name' => $request->business_name,
            'business_type' => $request->business_type,
            'description' => $request->description,
            'website' => $request->website,
            'instagram' => $request->instagram,
            'facebook' => $request->facebook,
            'whatsapp' => $request->whatsapp,
            'business_hours' => $request->business_hours, // JSON field
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business profile created successfully',
            'data' => new BusinessProfileResource($businessProfile),
        ], 201);
    }

    /**
     * Update business profile
     * PUT /api/business/profile
     */
    public function update(UpdateBusinessProfileRequest $request): JsonResponse
    {
        $businessProfile = $request->user()->businessProfile;


        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found. Please create one first.',
            ], 404);
        }

        // Update business profile
        $businessProfile->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Business profile updated successfully',
            'data' => new BusinessProfileResource($businessProfile->fresh()),
        ], 200);
    }

    /**
     * Delete business profile
     * DELETE /api/business/profile
     */
    public function destroy(Request $request): JsonResponse
    {
        $businessProfile = $request->user()->businessProfile;

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found',
            ], 404);
        }

        DB::transaction(function () use ($businessProfile) {
            $businessProfile->locations()->delete();
            $businessProfile->services()->delete();
            $businessProfile->products()->delete();
            
            // Delete business profile
            $businessProfile->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Business profile deleted successfully',
        ], 200);
    }

    /**
     * Get business profile by ID (public view)
     * GET /api/business/{id}
     */
    public function showPublic(string $id): JsonResponse
    {
        $businessProfile = BusinessProfile::with(['locations', 'user'])
            ->where('id', $id)
            ->orWhere('id', $id) 
            ->first();

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new BusinessProfileResource($businessProfile),
        ], 200);
    }

    /**
     * Search/browse businesses (for customers)
     * GET /api/business/search
     */
    public function search(Request $request): JsonResponse
    {
        $query = BusinessProfile::query()->with(['locations', 'user']);

        // Filter by business type
        if ($request->has('business_type')) {
            $query->where('business_type', $request->business_type);
        }

        // Filter by verified businesses
        if ($request->boolean('verified_only')) {
            $query->where('is_verified', true);
        }

        // Search by name or description
        if ($request->has('query')) {
            $searchTerm = $request->query;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('business_name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter by minimum rating
        if ($request->has('min_rating')) {
            $query->where('average_rating', '>=', $request->min_rating);
        }

        if ($request->has('latitude') && $request->has('longitude')) {
            $lat = $request->latitude;
            $lng = $request->longitude;
            $radius = $request->radius ?? 10; // Default 10km

            $query->whereHas('locations', function ($q) use ($lat, $lng, $radius) {
                $q->selectRaw("
                    *, 
                    (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                    sin(radians(latitude)))) AS distance
                ", [$lat, $lng, $lat])
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
            });
        }

        // Sort by rating or date
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if ($sortBy === 'rating') {
            $query->orderBy('average_rating', $sortOrder);
        } else {
            $query->orderBy('created_at', $sortOrder);
        }

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $businesses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => BusinessProfileResource::collection($businesses),
            'meta' => [
                'current_page' => $businesses->currentPage(),
                'per_page' => $businesses->perPage(),
                'total' => $businesses->total(),
                'last_page' => $businesses->lastPage(),
            ],
        ], 200);
    }
}
