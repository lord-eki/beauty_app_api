<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicesRequest;
use App\Http\Requests\UpdateServicesRequest;
use App\Models\Services;
use App\Http\Resources\ServiceResource;
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
    public function store(StoreServiceRequest $request): JsonResponse
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
     * Update a service
     */
    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
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
            foreach ($service->images as $image) {
                Storage::disk('public')->delete($image);
            }
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
            $filename = 'services/' . Str::uuid() . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('', $filename, 'public');
            $images[] = $path;
        }

        $service->update(['images' => $images]);

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'data' => [
                'images' => array_map(fn($img) => asset('storage/' . $img), $images),
                'service' => new ServiceResource($service->fresh()),
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
        Storage::disk('public')->delete($images[$imageIndex]);

        // Remove from array
        unset($images[$imageIndex]);
        $images = array_values($images); // Re-index array

        $service->update(['images' => $images]);

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
            'data' => new ServiceResource($service->fresh()),
        ], 200);
    }
}
