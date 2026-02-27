<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $business = $user->businessProfile;

        if (! $business) {
            return response()->json(['success' => false, 'message' => 'No business profile found'], 403);
        }

        $active = Subscription::where('business_profile_id', $business->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->exists();

        if (! $active) {
            return response()->json(['success' => false, 'message' => 'You need an active subscription to access this resource.', 'subscribe_url' => url('/api/subscription/pay')], 402);
        }

        return $next($request);
    }
}
