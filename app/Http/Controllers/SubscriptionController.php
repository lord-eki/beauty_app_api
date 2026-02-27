<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(protected MpesaService $mpesa) {}

    public function status(Request $request)
    {
        $business = $request->user()->businessProfile;

        if (! $business) {
            return $this->error('No business profile found', 404);

        }

        $subscription = Subscription::where('business_profile_id', $business->id)->orderByDesc('created_at')->first();

        if (! $subscription) {
            return $this->success([
                'has_subscription' => false,
                'subscription' => null,
            ]);
        }

        if ($subscription->isExpired() && $subscription->status === 'active') {
            $subscription->update(['status' => 'expired']);
        }

        return $this->success([
            'has_subscription' => $subscription->isActive(),
            'subscription' => $this->formatSubscription($subscription),
        ]);
    }

    public function pay(Request $request): JsonResponse
    {
        $request->validate([
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'phone' => ['required', 'string', 'regex:/^(\+?254|0)[17]\d{8}$/'],
        ]);

        $business = $request->user()->businessProfile;

        if (! $business) {
            return $this->error('No business profile found', 404);
        }

        $active = Subscription::where('business_profile_id', $business->id)->where('status', 'active')->where('end_date', '>', now())->first();

        if ($active) {
            return $this->error('You already have an active subscription. It expires on '.$active->end_date->format('d M Y').'.', 422);
        }

        $plans = config('mpesa.plans.basic');
        $cycle = $request->billing_cycle;
        $amount = $plans[$cycle];
        $phone = $this->mpesa->formatPhone($request->phone);

        // Create a pending subscription record first
        $subscription = Subscription::create([
            'business_profile_id' => $business->id,
            'plan_name' => 'Basic',
            'amount' => $amount,
            'currency' => 'KES',
            'billing_cycle' => $cycle,
            'start_date' => now()->toDateString(),
            'end_date' => $this->calculateEndDate($cycle),
            'status' => 'pending',
            'payment_phone' => $phone,
        ]);

        try {
            $result = $this->mpesa->stkPush(
                phone: $phone,
                amount: $amount,
                reference: 'SUB-'.$subscription->id,
                description: "Beauty Connect {$cycle} subscription"
            );

            $subscription->update([
                'mpesa_transaction_id' => $result['CheckoutRequestID'] ?? null,
            ]);

            Log::info('STK push initiated', ['subscription_id' => $subscription->id, 'result' => $result]);

            return $this->success([
                'message' => 'Payment prompt sent to '.$request->phone.'. Enter your MPesa PIN to complete.',
                'subscription_id' => $subscription->id,
                'checkout_request_id' => $result['CheckoutRequestID'] ?? null,
                'amount' => $amount,
                'billing_cycle' => $cycle,
            ], 'STK push initiated successfully.');

        } catch (\Throwable $e) {
            $subscription->update(['status' => 'cancelled']);
            Log::error('STK push error', ['error' => $e->getMessage()]);

            return $this->error('Failed to initiate payment. Please try again.', 500);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $business = $request->user()->businessProfile;

        if (! $business) {
            return $this->error('No business profile found.', 404);
        }

        $subscriptions = Subscription::where('business_profile_id', $business->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return $this->success(
            $subscriptions->through(fn ($s) => $this->formatSubscription($s)),
            'Subscription history retrieved.'
        );
    }

    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'checkout_request_id' => ['required', 'string'],
        ]);

        $business = $request->user()->businessProfile;

        if (! $business) {
            return $this->error('No business profile found.', 404);
        }

        $subscription = Subscription::where('business_profile_id', $business->id)
            ->where('mpesa_transaction_id', $request->checkout_request_id)
            ->first();

        if (! $subscription) {
            return $this->error('Subscription not found.', 404);
        }

        if (in_array($subscription->status, ['active', 'cancelled'])) {
            return $this->success(['subscription' => $this->formatSubscription($subscription)]);
        }

        try {
            $result = $this->mpesa->stkQuery($request->checkout_request_id);
            $resultCode = $result['ResultCode'] ?? null;

            if ($resultCode === '0') {
                $subscription->update(['status' => 'active']);
            } elseif ($resultCode !== null) {
                $subscription->update(['status' => 'cancelled']);
            }

        } catch (\Throwable $e) {
            Log::error('STK query error', ['error' => $e->getMessage()]);
        }

        return $this->success(['subscription' => $this->formatSubscription($subscription->fresh())]);
    }

    private function calculateEndDate(string $cycle): string
    {
        return match ($cycle) {
            'yearly' => now()->addYear()->toDateString(),
            default => now()->addMonth()->toDateString(),
        };
    }

    private function formatSubscription(Subscription $s): array
    {
        return [
            'id' => $s->id,
            'plan_name' => $s->plan_name,
            'billing_cycle' => $s->billing_cycle,
            'amount' => $s->amount,
            'currency' => $s->currency,
            'status' => $s->status,
            'is_active' => $s->isActive(),
            'start_date' => $s->start_date?->format('Y-m-d'),
            'end_date' => $s->end_date?->format('Y-m-d'),
            'days_remaining' => $s->isActive() ? now()->diffInDays($s->end_date) : 0,
            'mpesa_transaction_id' => $s->mpesa_transaction_id,
            'payment_phone' => $s->payment_phone,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }

    private function success(mixed $data, string $message = 'Success'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
