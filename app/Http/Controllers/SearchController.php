<?php

namespace App\Http\Controllers;

use App\Http\Resources\BusinessProfileResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ServiceResource;
use App\Models\BusinessProfile;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search for services
     */
    public function services(Request $request): JsonResponse
    {
        $query = Service::query()->active()->with(['businessProfile', 'category']);

        // Text search
        if ($request->filled('query')) {
            $searchTerm = $request->query;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where('price_min', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price_max', '<=', $request->max_price);
        }

        // Home service filter
        if ($request->filled('home_service')) {
            $query->where('is_home_service', $request->boolean('home_service'));
        }

        // Location-based search (if lat/lng provided)
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $radius = $request->input('radius', 10); // Default 10km

            $query->whereHas('businessProfile.locations', function ($q) use ($lat, $lng, $radius) {
                $q->selectRaw(
                    "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                    [$lat, $lng, $lat]
                )->having('distance', '<=', $radius);
            });
        }

        $perPage = $request->input('per_page', 15);
        $services = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ServiceResource::collection($services),
            'meta' => [
                'pagination' => [
                    'current_page' => $services->currentPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                    'total_pages' => $services->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Search for products
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::query()->active()->with(['businessProfile', 'category']);

        // Text search
        if ($request->filled('query')) {
            $searchTerm = $request->query;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('brand', 'like', "%{$searchTerm}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Brand filter
        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // In stock filter
        if ($request->filled('in_stock')) {
            if ($request->boolean('in_stock')) {
                $query->inStock();
            }
        }

        // Location-based search
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $radius = $request->input('radius', 10);

            $query->whereHas('businessProfile.locations', function ($q) use ($lat, $lng, $radius) {
                $q->selectRaw(
                    "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                    [$lat, $lng, $lat]
                )->having('distance', '<=', $radius);
            });
        }

        $perPage = $request->input('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'total_pages' => $products->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Search for businesses
     */
    public function businesses(Request $request): JsonResponse
    {
        $query = BusinessProfile::query()->with(['locations', 'reviews']);

        // Text search
        if ($request->filled('query')) {
            $searchTerm = $request->query;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('business_name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Business type filter
        if ($request->filled('type')) {
            $query->where('business_type', $request->type);
        }

        // Verified only filter
        if ($request->filled('verified')) {
            if ($request->boolean('verified')) {
                $query->verified();
            }
        }

        // Minimum rating filter
        if ($request->filled('min_rating')) {
            $query->where('average_rating', '>=', $request->min_rating);
        }

        // Location-based search
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $radius = $request->input('radius', 10);

            $query->whereHas('locations', function ($q) use ($lat, $lng, $radius) {
                $q->selectRaw(
                    "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                    [$lat, $lng, $lat]
                )->having('distance', '<=', $radius);
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        if ($sortBy === 'rating') {
            $query->orderBy('average_rating', $sortOrder);
        } elseif ($sortBy === 'reviews') {
            $query->orderBy('total_reviews', $sortOrder);
        } else {
            $query->orderBy('created_at', $sortOrder);
        }

        $perPage = $request->input('per_page', 15);
        $businesses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => BusinessProfileResource::collection($businesses),
            'meta' => [
                'pagination' => [
                    'current_page' => $businesses->currentPage(),
                    'per_page' => $businesses->perPage(),
                    'total' => $businesses->total(),
                    'total_pages' => $businesses->lastPage(),
                ],
            ],
        ], 200);
    }
}