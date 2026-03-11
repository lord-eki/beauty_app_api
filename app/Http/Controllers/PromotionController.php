<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    // =========================================================================
    // BUSINESS — manage their own promotions
    // =========================================================================

    /**
     * GET /api/business/promotions
     * List all promotions for the authenticated business.
     */
    public function index(Request $request): JsonResponse
    {
        $business = $this->getAuthBusiness($request);

        $promotions = Promotion::forBusiness($business->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $promotions,
        ]);
    }

    /**
     * POST /api/business/promotions
     */
    public function store(Request $request): JsonResponse
    {
        $business = $this->getAuthBusiness($request);

        $data = $request->validate($this->validationRules());

        $promotion = Promotion::create(array_merge(
            $data,
            ['business_profile_id' => $business->id]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Promotion created successfully.',
            'data'    => $promotion,
        ], 201);
    }

    /**
     * GET /api/business/promotions/{promotion}
     */
    public function show(Request $request, Promotion $promotion): JsonResponse
    {
        $this->authorizePromotion($request, $promotion);

        $promotion->load('usages');

        return response()->json(['success' => true, 'data' => $promotion]);
    }

    /**
     * PUT /api/business/promotions/{promotion}
     */
    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $this->authorizePromotion($request, $promotion);

        $data = $request->validate($this->validationRules(update: true));

        $promotion->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Promotion updated successfully.',
            'data'    => $promotion->fresh(),
        ]);
    }

    /**
     * DELETE /api/business/promotions/{promotion}
     */
    public function destroy(Request $request, Promotion $promotion): JsonResponse
    {
        $this->authorizePromotion($request, $promotion);

        $promotion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promotion deleted.',
        ]);
    }

    // =========================================================================
    // PUBLIC / CUSTOMER-FACING
    // =========================================================================

    /**
     * GET /api/business/{businessProfile}/promotions/active
     * Public: list currently active promotions for a business.
     */
    public function activeForBusiness(BusinessProfile $businessProfile): JsonResponse
    {
        $promotions = Promotion::forBusiness($businessProfile->id)
            ->active()
            ->get(['id', 'title', 'description', 'promotion_type',
                   'discount_percentage', 'discount_amount',
                   'minimum_purchase', 'promo_code',
                   'start_date', 'end_date']);

        return response()->json(['success' => true, 'data' => $promotions]);
    }

    /**
     * GET /api/promotions/validate?code={code}&subtotal={subtotal}&business_id={id}
     * Validate a promo code and return the calculated discount.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code'        => 'required|string',
            'subtotal'    => 'required|numeric|min:0',
            'business_id' => 'required|integer|exists:business_profiles,id',
        ]);

        $promotion = Promotion::active()
            ->forBusiness((int) $request->business_id)
            ->where('promo_code', $request->code)
            ->first();

        if (!$promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired promo code.',
            ], 422);
        }

        if ($promotion->hasReachedGlobalLimit()) {
            return response()->json([
                'success' => false,
                'message' => 'This promotion has reached its usage limit.',
            ], 422);
        }

        $user = $request->user();
        if ($user && !$promotion->isEligibleForUser($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You have already used this promotion the maximum number of times.',
            ], 422);
        }

        $discount = $promotion->calculateDiscount((float) $request->subtotal);

        return response()->json([
            'success' => true,
            'data'    => [
                'promotion_id'   => $promotion->id,
                'title'          => $promotion->title,
                'discount'       => $discount,
                'final_subtotal' => round((float) $request->subtotal - $discount, 2),
            ],
        ]);
    }

    /**
     * POST /api/promotions/redeem
     * Record a promotion redemption after a successful order or appointment.
     * Called internally (e.g. from OrderController / AppointmentController).
     */
    public function redeem(Request $request): JsonResponse
    {
        $request->validate([
            'promotion_id'   => 'required|integer|exists:promotions,id',
            'subtotal'       => 'required|numeric|min:0',
            'order_id'       => 'nullable|integer|exists:orders,id',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
        ]);

        $promotion = Promotion::findOrFail($request->promotion_id);
        $user      = $request->user();

        // Re-validate eligibility inside a transaction to prevent race conditions
        return DB::transaction(function () use ($promotion, $user, $request): JsonResponse {

            // Lock the row while we check
            $promo = Promotion::lockForUpdate()->find($promotion->id);

            if (!$promo->isCurrentlyActive()) {
                return response()->json(['success' => false, 'message' => 'Promotion is no longer active.'], 422);
            }

            if ($promo->hasReachedGlobalLimit()) {
                return response()->json(['success' => false, 'message' => 'Promotion usage limit reached.'], 422);
            }

            if (!$promo->isEligibleForUser($user->id)) {
                return response()->json(['success' => false, 'message' => 'Per-user usage limit reached.'], 422);
            }

            $discount = $promo->calculateDiscount((float) $request->subtotal);

            PromotionUsage::create([
                'promotion_id'   => $promo->id,
                'user_id'        => $user->id,
                'order_id'       => $request->order_id,
                'appointment_id' => $request->appointment_id,
                'discount_applied' => $discount,
            ]);

            $promo->increment('usage_count');

            return response()->json([
                'success'  => true,
                'message'  => 'Promotion redeemed.',
                'data'     => ['discount_applied' => $discount],
            ]);
        });
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

    private function authorizePromotion(Request $request, Promotion $promotion): void
    {
        $business = $this->getAuthBusiness($request);

        abort_unless(
            $promotion->business_profile_id === $business->id,
            403,
            'You do not own this promotion.'
        );
    }

    private function validationRules(bool $update = false): array
    {
        $required = $update ? 'sometimes|required' : 'required';

        return [
            'title'               => "{$required}|string|max:255",
            'description'         => 'nullable|string',
            'promotion_type'      => "{$required}|in:percentage,fixed_amount,buy_x_get_y,free_service,bundle_deal,first_time_customer,loyalty_reward",
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount'     => 'nullable|numeric|min:0',
            'buy_quantity'        => 'nullable|integer|min:1',
            'get_quantity'        => 'nullable|integer|min:1',
            'minimum_purchase'    => 'nullable|numeric|min:0',
            'maximum_discount'    => 'nullable|numeric|min:0',
            'usage_limit'         => 'nullable|integer|min:1',
            'user_usage_limit'    => 'nullable|integer|min:1',
            'target_type'         => 'nullable|in:all,services,products,categories,specific_items',
            'target_items'        => 'nullable|array',
            'applicable_locations'=> 'nullable|array',
            'start_date'          => "{$required}|date|date_format:Y-m-d",
            'end_date'            => "{$required}|date|date_format:Y-m-d|after_or_equal:start_date",
            'promo_code'          => 'nullable|string|max:50|unique:promotions,promo_code' . ($update ? ',' . request()->route('promotion')?->id : ''),
            'is_active'           => 'nullable|boolean',
        ];
    }
}