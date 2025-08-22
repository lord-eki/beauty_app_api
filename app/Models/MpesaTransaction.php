<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\MpesaTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_request_id',
        'checkout_request_id',
        'mpesa_receipt_number',
        'amount',
        'phone_number',
        'account_reference',
        'transaction_desc',
        'transaction_type',
        'reference_id',
        'status',
        'result_desc',
        'callback_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'callback_data' => 'array',
    ];

    public function getReference()
    {
        switch ($this->transaction_type) {
            case 'subscription':
                return $this->belongsTo(Subscription::class, 'reference_id');
            case 'order':
                return $this->belongsTo(Order::class, 'reference_id');
            case 'appointment':
                return $this->belongsTo(Appointment::class, 'reference_id');
            default:
                return null;
        }
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function hasFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled']);
    }
}
