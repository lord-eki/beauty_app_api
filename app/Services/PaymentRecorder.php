<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\PaymentRecord;
use Illuminate\Support\Facades\DB;


class PaymentRecorder
{
    public const METHODS = ['cash', 'mpesa', 'bank_transfer', 'other'];

    public static function record(Order|Appointment $payable, int $businessProfileId, array $data, int $userId): PaymentRecord
    {
        return DB::transaction(function () use ($payable, $businessProfileId, $data, $userId) {
            $payable->refresh();

            $total   = (float) ($payable->total_amount ?? 0);
            $already = (float) $payable->payments()->sum('amount');
            $amount  = isset($data['amount']) && $data['amount'] !== null
                ? (float) $data['amount']
                : max($total - $already, 0);

            $record = $payable->payments()->create([
                'business_profile_id' => $businessProfileId,
                'amount'      => $amount,
                'method'      => $data['method'],
                'reference'   => $data['reference'] ?? null,
                'paid_at'     => $data['paid_at'] ?? now(),
                'recorded_by' => $userId,
                'notes'       => $data['notes'] ?? null,
            ]);

            if ($already + $amount + 0.009 >= $total) {
                $payable->payment_status = 'paid';
            }
            if ($payable instanceof Order) {
                $payable->payment_method    = $data['method'];
                $payable->payment_reference = $data['reference'] ?? $payable->payment_reference;
            }
            $payable->save();

            return $record;
        });
    }
}
