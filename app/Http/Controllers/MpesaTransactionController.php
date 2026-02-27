<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MpesaTransactionController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('MPesa callback received', ['payload' => $payload]);

        try {
            $body = $payload['Body']['stkCallback'] ?? null;
            $resultCode = $body['ResultCode'] ?? null;
            $checkoutReqId = $body['CheckoutRequestID'] ?? null;

            if (! $checkoutReqId) {
                Log::warning('MPesa callback missing CheckoutRequestID', $payload);

                return $this->acknowledge();
            }

            $subscription = Subscription::where('mpesa_transaction_id', $checkoutReqId)->first();

            if (! $subscription) {
                Log::warning('MPesa callback: subscription not found', ['checkout_id' => $checkoutReqId]);

                return $this->acknowledge();
            }

            if ($resultCode === 0) {
                // Payment successful - extract MPesa transaction details
                $items = collect($body['CallbackMetadata']['Item'] ?? []);
                $mpesaCode = $items->firstWhere('Name', 'MpesaReceiptNumber')['Value'] ?? null;
                $phone = $items->firstWhere('Name', 'PhoneNumber')['Value'] ?? $subscription->payment_phone;

                $subscription->update([
                    'status' => 'active',
                    'mpesa_transaction_id' => $mpesaCode ?? $checkoutReqId, // Store receipt number
                    'payment_phone' => (string) $phone,
                ]);

                Log::info('Subscription activated', [
                    'subscription_id' => $subscription->id,
                    'mpesa_code' => $mpesaCode,
                ]);

                // TODO: Fire event for notifications
                // event(new SubscriptionActivated($subscription));

            } else {
                // Payment failed or cancelled by user
                $resultDesc = $body['ResultDesc'] ?? 'Payment failed';
                Log::info('MPesa payment failed', [
                    'subscription_id' => $subscription->id,
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc,
                ]);

                $subscription->update(['status' => 'cancelled']);
            }

        } catch (\Throwable $e) {
            Log::error('MPesa callback processing error', ['error' => $e->getMessage(), 'payload' => $payload]);
        }

        // Always return 200 to Safaricom so they stop retrying
        return $this->acknowledge();
    }

    private function acknowledge(): JsonResponse
    {
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }
}
