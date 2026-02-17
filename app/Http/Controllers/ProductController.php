<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Get all products for authenticated business
     * GET /api/business/products
     */
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No business profile found. Create a business profile first.',
            ], 404);
        }

        $products = $profile->products()
            ->with('category')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
        ], 200);
    }

    /**
     * Create a new product
     * POST /api/business/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No business profile found. Create a business profile first.',
            ], 404);
        }

        $product = $profile->products()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => new ProductResource($product->load('category')),
        ], 201);
    }

    /**
     * Display a specific product
     * GET /api/business/products/{product}
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($product->load(['category', 'businessProfile'])),
        ], 200);
    }

    /**
     * Update a product
     * PUT /api/business/products/{product}
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        if ($product->business_profile_id !== $request->user()->businessProfile?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $product->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => new ProductResource($product->fresh(['category'])),
        ], 200);
    }

    /**
     * Delete a product
     * DELETE /api/business/products/{product}
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($product->business_profile_id !== $request->user()->businessProfile?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Delete all images from storage
        if ($product->images) {
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ], 200);
    }

    /**
     * Upload product images
     * POST /api/business/products/{product}/images
     */
    public function uploadImages(Request $request, Product $product): JsonResponse
    {
        if ($product->business_profile_id !== $request->user()->businessProfile?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'images'   => ['required', 'array', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $images = $product->images ?? [];

        foreach ($request->file('images') as $image) {
            $filename = 'products/' . Str::uuid() . '.' . $image->getClientOriginalExtension();
            $path     = $image->storeAs('', $filename, 'public');
            $images[] = $path;
        }

        $product->update(['images' => $images]);

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'data'    => new ProductResource($product->fresh()),
        ], 200);
    }

    /**
     * Delete a specific product image
     * DELETE /api/business/products/{product}/images/{imageIndex}
     */
    public function deleteImage(Request $request, Product $product, int $imageIndex): JsonResponse
    {
        if ($product->business_profile_id !== $request->user()->businessProfile?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $images = $product->images ?? [];

        if (!isset($images[$imageIndex])) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found',
            ], 404);
        }

        Storage::disk('public')->delete($images[$imageIndex]);
        unset($images[$imageIndex]);
        $images = array_values($images);

        $product->update(['images' => $images]);

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
            'data'    => new ProductResource($product->fresh()),
        ], 200);
    }

    /**
     * Public: Get products for a specific business
     * GET /api/business/{businessProfile}/products
     */
    public function public(Request $request, $businessProfileId): JsonResponse
    {
        $products = Product::where('business_profile_id', $businessProfileId)
            ->where('is_active', true)
            ->with('category')
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('brand'),       fn($q) => $q->where('brand', $request->brand))
            ->when($request->filled('min_price'),   fn($q) => $q->where('price', '>=', $request->min_price))
            ->when($request->filled('max_price'),   fn($q) => $q->where('price', '<=', $request->max_price))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => ProductResource::collection($products),
            'meta'    => [
                'current_page' => $products->currentPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
                'last_page'    => $products->lastPage(),
            ],
        ], 200);
    }
}