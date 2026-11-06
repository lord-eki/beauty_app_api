<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicesRequest;
use App\Http\Requests\UpdateServicesRequest;
use App\Http\Requests\UploadServiceImagesRequest;
use App\Models\Service;
use App\Http\Resources\ServiceResource;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServicesController extends Controller
{
/**
     * Get all services for authenticated business
     */
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No business profile found',
            ], 404);
        }

        $services = $profile->services()
            ->with('category')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => ServiceResource::collection($services),
        ], 200);
    }

    /**
     * Create a new service
     */
    public function store(StoreServicesRequest $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No business profile found',
            ], 404);
        }

        $service = $profile->services()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully',
            'data' => new ServiceResource($service->load('category')),
        ], 201);
    }

    /**
     * Display a specific service
     */
    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ServiceResource($service->load(['category', 'businessProfile', 'reviews'])),
        ], 200);
    }

    /**
     * Public service detail (customers browsing)
     * GET /api/services/{service}
     */
    public function showPublic(Service $service): JsonResponse
    {
        if (! $service->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found',
            ], 404);
        }

        $service->load([
            'category',
            'businessProfile',
            'businessProfile.locations',
            'businessProfile.availabilities',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ServiceResource($service),
        ], 200);
    }

    /**
     * Update a service
     */
    public function update(UpdateServicesRequest $request, Service $service): JsonResponse
    {
        // Ensure the service belongs to the authenticated user's business
        if ($service->business_profile_id !== $request->user()->businessProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $service->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully',
            'data' => new ServiceResource($service->fresh(['category'])),
        ], 200);
    }

    /**
     * Delete a service
     */
    public function destroy(Request $request, Service $service): JsonResponse
    {
        // Ensure the service belongs to the authenticated user's business
        if ($service->business_profile_id !== $request->user()->businessProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Delete all images
        if ($service->images) {
            Media::delete($service->images);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully',
        ], 200);
    }

    /**
     * Upload service images
     */
    public function uploadImages(UploadServiceImagesRequest $request, Service $service): JsonResponse
    {
        // Ensure the service belongs to the authenticated user's business
        if ($service->business_profile_id !== $request->user()->businessProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $images = $service->images ?? [];

        foreach ($request->file('images') as $image) {
            $images[] = Media::store($image, 'services');
        }

        $service->update(['images' => $images]);

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'data' => [
                'images' => Media::url($images),
                'service' => new ServiceResource($service->fresh('category')),
            ],
        ], 200);
    }

    /**
     * Delete a specific image
     */
    public function deleteImage(Request $request, Service $service, $imageIndex): JsonResponse
    {
        // Ensure the service belongs to the authenticated user's business
        if ($service->business_profile_id !== $request->user()->businessProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $images = $service->images ?? [];

        if (!isset($images[$imageIndex])) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found',
            ], 404);
        }

        // Delete from storage
        Media::delete($images[$imageIndex]);

        // Remove from array
        unset($images[$imageIndex]);
        $images = array_values($images); 

        $service->update(['images' => $images]);

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
            'data' => new ServiceResource($service->fresh()),
        ], 200);
    }
}