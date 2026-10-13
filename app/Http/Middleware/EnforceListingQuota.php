<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class EnforceListingQuota
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->user()?->businessProfile;

        if (! $business) {
            return response()->json(['success' => false, 'message' => 'No business profile found'], 403);
        }

        if ($business->hasActiveSubscription()) {
            return $next($request);
        }

        $limit = (int) config('subscription.free_listing_limit', 2);
        $used  = $business->listingCount();

        if ($used >= $limit) {
            return response()->json([
                'success' => false,
                'code'    => 'listing_limit_reached',
                'message' => "You've used your {$limit} free " . ($limit === 1 ? 'listing' : 'listings') . '. Subscribe to add more.',
                'data'    => [
                    'free_limit'    => $limit,
                    'used'          => $used,
                    'monthly_price' => (float) config('subscription.plans.basic.monthly'),
                ],
            ], 402);
        }

        return $next($request);
    }
}