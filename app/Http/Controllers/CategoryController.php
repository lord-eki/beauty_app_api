<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
   /**
     * Get all active categories with hierarchy
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::active()
            ->parents()->with('children')
            ->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ], 200);
    }

    /**
     * Get a specific category with its children
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category->load('children')),
        ], 200);
    }

    /**
     * Get services in a category
     */
    public function services(Category $category, Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $services = $category->services()
            ->active()
            ->with(['businessProfile'])
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $services,
        ], 200);
    }

    /**
     * Get products in a category
     */
    public function products(Category $category, Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $products = $category->products()
            ->active()
            ->with(['businessProfile'])
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
        ], 200);
    }
}
