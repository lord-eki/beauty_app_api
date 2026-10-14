<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessProfileRequest;
use App\Http\Requests\UpdateBusinessProfileRequest;
use App\Http\Resources\BusinessProfileResource;
use App\Models\Appointment;
use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\PaymentRecord;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BusinessProfileController extends Controller
{
    /**
     * Get authenticated provider's business profile
     * GET /api/business/profile
     */
    public function show(Request $request): JsonResponse
    {

        if (! $request->user()->isProvider()) {
            return response()->json([
                'success' => false,
                'message' => 'Only providers can access business profiles',
            ], 403);
        }

        $businessProfile = $request->user()->businessProfile;


        if (! $businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found. Please create one first.',
            ], 404);
        }

        $businessProfile->load('subscriptions');

        return response()->json([
            'success' => true,
            'data' => new BusinessProfileResource($businessProfile),
        ], 200);
    }

    /**
     * Dashboard analytics for the authenticated business
     * GET /api/business/analytics   (alias: /api/business/statistics)
     */
    public function analytics(Request $request): JsonResponse
    {
        $profile = $request->user()->businessProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found. Please create one first.',
            ], 404);
        }

        $id        = $profile->id;
        $thisStart = now()->startOfMonth();
        $lastStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastEnd   = now()->subMonthNoOverflow()->endOfMonth();

        $sumPayments = fn ($from, $to) => (float) PaymentRecord::where('business_profile_id', $id)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        $revenueThisMonth = $sumPayments($thisStart, now());
        $revenueLastMonth = $sumPayments($lastStart, $lastEnd);

        $revenueByMethod = PaymentRecord::where('business_profile_id', $id)
            ->where('paid_at', '>=', $thisStart)
            ->selectRaw('method, SUM(amount) as amt')
            ->groupBy('method')->pluck('amt', 'method')
            ->map(fn ($v) => (float) $v);

        $appointmentsThisMonth = Appointment::where('business_profile_id', $id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('appointment_date', [$thisStart->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();

        $ordersThisMonth = Order::where('business_profile_id', $id)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $thisStart)
            ->count();

        
        $customersThisMonth = Appointment::where('business_profile_id', $id)
            ->where('created_at', '>=', $thisStart)->pluck('customer_id')
            ->merge(Order::where('business_profile_id', $id)
                ->where('created_at', '>=', $thisStart)->pluck('customer_id'))
            ->unique();

        $returning = Appointment::where('business_profile_id', $id)
            ->where('created_at', '<', $thisStart)
            ->whereIn('customer_id', $customersThisMonth)->pluck('customer_id')
            ->merge(Order::where('business_profile_id', $id)
                ->where('created_at', '<', $thisStart)
                ->whereIn('customer_id', $customersThisMonth)->pluck('customer_id'))
            ->unique();

        $newCustomers = $customersThisMonth->diff($returning)->count();

        $from = now()->subDays(29)->startOfDay();

        $paidByDay = PaymentRecord::where('business_profile_id', $id)
            ->where('paid_at', '>=', $from)
            ->selectRaw('DATE(paid_at) as d, SUM(amount) as amt')
            ->groupBy('d')->pluck('amt', 'd');

        $revenueByDay = [];
        for ($i = 0; $i < 30; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $revenueByDay[] = [
                'date'   => $day,
                'amount' => (float) ($paidByDay[$day] ?? 0),
            ];
        }

        $popularServices = Appointment::query()
            ->where('appointments.business_profile_id', $id)
            ->whereNotIn('appointments.status', ['cancelled', 'no_show'])
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->groupBy('services.id', 'services.name')
            ->selectRaw('services.id as service_id, services.name as service_name,
                         COUNT(*) as booking_count,
                         COALESCE(SUM(appointments.total_amount), 0) as revenue')
            ->orderByDesc('booking_count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'service_id'    => $r->service_id,
                'service_name'  => $r->service_name,
                'booking_count' => (int) $r->booking_count,
                'revenue'       => (float) $r->revenue,
            ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue_this_month'      => $revenueThisMonth,
                'total_revenue_last_month'      => $revenueLastMonth,
                'total_appointments_this_month' => $appointmentsThisMonth,
                'total_orders_this_month'       => $ordersThisMonth,
                'new_customers_this_month'      => $newCustomers,
                'average_rating'                => (float) ($profile->average_rating ?? 0),
                'revenue_by_day'                => $revenueByDay,
                'revenue_by_method'             => $revenueByMethod,
                'popular_services'              => $popularServices,
            ],
        ], 200);
    }

    /**
     * Create a new business profile
     * POST /api/business/profile
     */
    public function store(StoreBusinessProfileRequest $request): JsonResponse
    {
        if (! $request->user()->isProvider()) {
            return response()->json([
                'success' => false,
                'message' => 'Only providers can create business profiles',
            ], 403);
        }

        if ($request->user()->businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a business profile. Use update instead.',
            ], 422);
        }

        $businessProfile = $request->user()->businessProfile()->create([
            'business_name' => $request->business_name,
            'business_type' => $request->business_type,
            'description' => $request->description,
            'website' => $request->website,
            'instagram' => $request->instagram,
            'facebook' => $request->facebook,
            'whatsapp' => $request->whatsapp,
            'business_hours' => $request->business_hours,
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

        if (! $businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found. Please create one first.',
            ], 404);
        }

        $businessProfile->update($request->validated());

        CacheService::forgetBusiness($businessProfile->id);
        CacheService::forgetSearchResults();

        return response()->json([
            'success' => true,
            'message' => 'Business profile updated successfully',
            'data' => new BusinessProfileResource($businessProfile->fresh('subscriptions')),
        ], 200);
    }

    /**
     * Delete business profile
     * DELETE /api/business/profile
     */
    public function destroy(Request $request): JsonResponse
    {
        $businessProfile = $request->user()->businessProfile;

        if (! $businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found',
            ], 404);
        }

        DB::transaction(function () use ($businessProfile) {
            $businessProfile->locations()->delete();
            $businessProfile->services()->delete();
            $businessProfile->products()->delete();

            $businessProfile->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Business profile deleted successfully',
        ], 200);
    }

    /**
     * Get business profile 
     * GET /api/business/{id}
     */
    public function showPublic(string $id): JsonResponse
    {
        $businessProfile = BusinessProfile::with(['locations', 'user'])
            ->where('id', $id)
            ->orWhere('id', $id)
            ->first();

        if (! $businessProfile) {
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
     * Search/browse businesses 
     * GET /api/business/search
     */
    public function search(Request $request): JsonResponse
    {
        $query = BusinessProfile::query()->with(['locations', 'user']);

        if ($request->has('business_type')) {
            $query->where('business_type', $request->business_type);
        }

        if ($request->boolean('verified_only')) {
            $query->where('is_verified', true);
        }

        if ($request->has('query')) {
            $searchTerm = (string) $request->input('query');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('business_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        if ($request->has('min_rating')) {
            $query->where('average_rating', '>=', $request->min_rating);
        }

        if ($request->has('latitude') && $request->has('longitude')) {
            $lat = $request->latitude;
            $lng = $request->longitude;
            $radius = $request->radius ?? 10; 

            $query->whereHas('locations', function ($q) use ($lat, $lng, $radius) {
                $q->selectRaw('
                    *, 
                    (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                    sin(radians(latitude)))) AS distance
                ', [$lat, $lng, $lat])
                    ->having('distance', '<=', $radius)
                    ->orderBy('distance');
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if ($sortBy === 'rating') {
            $query->orderBy('average_rating', $sortOrder);
        } else {
            $query->orderBy('created_at', $sortOrder);
        }

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