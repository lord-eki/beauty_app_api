<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\Review;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewController extends Controller
{
    /**
     * Public: Get all reviews for a business
     * GET /api/business/{businessProfile}/reviews
     */
    public function index(Request $request, BusinessProfile $businessProfile): JsonResponse
    {
        $reviews = $businessProfile->reviews()
            ->with('user:id,first_name,last_name,profile_image')
            ->when($request->filled('rating'),     fn($q) => $q->where('rating', $request->rating))
            ->when($request->filled('service_id'), fn($q) => $q->where('service_id', $request->service_id))
            ->when($request->filled('product_id'), fn($q) => $q->where('product_id', $request->product_id))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => ReviewResource::collection($reviews),
            'meta'    => [
                'current_page'   => $reviews->currentPage(),
                'per_page'       => $reviews->perPage(),
                'total'          => $reviews->total(),
                'last_page'      => $reviews->lastPage(),
                'average_rating' => round($businessProfile->average_rating, 2),
                'total_reviews'  => $businessProfile->total_reviews,
            ],
        ]);
    }

    /**
     * Customer: Submit a review
     * POST /api/reviews
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_profile_id' => 'required|integer|exists:business_profiles,id',
            'service_id'          => 'nullable|integer|exists:services,id',
            'product_id'          => 'nullable|integer|exists:products,id',
            'rating'              => 'required|integer|between:1,5',
            'comment'             => 'nullable|string|max:2000',
            'images'              => 'nullable|array|max:5',
            'images.*'            => 'image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $userId = $request->user()->id;

        // Enforce unique constraint — one review per user per business per service/product
        $exists = Review::where('user_id', $userId)
            ->where('business_profile_id', $data['business_profile_id'])
            ->when(
                isset($data['service_id']),
                fn($q) => $q->where('service_id', $data['service_id']),
                fn($q) => $q->whereNull('service_id')
            )
            ->when(
                isset($data['product_id']),
                fn($q) => $q->where('product_id', $data['product_id']),
                fn($q) => $q->whereNull('product_id')
            )
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this business for the selected service or product.',
            ], 422);
        }

        // Handle image uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $filename     = 'reviews/' . Str::uuid() . '.' . $image->getClientOriginalExtension();
                $imagePaths[] = $image->storeAs('', $filename, 'public');
            }
        }

        $review = Review::create([
            'user_id'             => $userId,
            'business_profile_id' => $data['business_profile_id'],
            'service_id'          => $data['service_id'] ?? null,
            'product_id'          => $data['product_id'] ?? null,
            'rating'              => $data['rating'],
            'comment'             => $data['comment'] ?? null,
            'images'              => $imagePaths ?: null,
        ]);

        $this->recalculateBusinessRating($data['business_profile_id']);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data'    => new ReviewResource($review->load('user:id,first_name,last_name,profile_image')),
        ], 201);
    }

    /**
     * Customer: Update their own review
     * PUT /api/reviews/{review}
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'rating'  => 'sometimes|required|integer|between:1,5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review->update($data);

        if (isset($data['rating'])) {
            $this->recalculateBusinessRating($review->business_profile_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully.',
            'data'    => new ReviewResource($review->fresh()->load('user:id,first_name,last_name,profile_image')),
        ]);
    }

    /**
     * Customer: Delete their own review
     * DELETE /api/reviews/{review}
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $businessProfileId = $review->business_profile_id;

        if ($review->images) {
            foreach ($review->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $review->delete();

        $this->recalculateBusinessRating($businessProfileId);

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully.',
        ]);
    }

    // -------------------------------------------------------------------------

    private function recalculateBusinessRating(int $businessProfileId): void
    {
        $business = BusinessProfile::find($businessProfileId);

        if (!$business) {
            return;
        }

        $stats = Review::where('business_profile_id', $businessProfileId)
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        $business->update([
            'average_rating' => round((float) ($stats->average ?? 0), 2),
            'total_reviews'  => (int) ($stats->total ?? 0),
        ]);
    }
}