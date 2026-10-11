<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Shape matches what the mobile app expects: plain numbers (not "1500.00"
     * strings), product names/thumbnails on each item, customer contact for the
     * business, and the payments the business has recorded.
     */
    public function toArray(Request $request): array
    {
        $date = fn ($v) => $v instanceof \Carbon\CarbonInterface ? $v->toDateString() : $v;

        return [
            'id'                  => $this->id,
            'customer_id'         => $this->customer_id,
            'business_profile_id' => $this->business_profile_id,
            'business_name'       => $this->whenLoaded('businessProfile', fn () => $this->businessProfile?->business_name),
            'order_number'        => $this->order_number,
            'subtotal'            => (float) $this->subtotal,
            'discount_amount'     => (float) $this->discount_amount,
            'tax_amount'          => (float) $this->tax_amount,
            'delivery_fee'        => (float) $this->delivery_fee,
            'total_amount'        => (float) $this->total_amount,
            'status'              => $this->status,
            'payment_status'      => $this->payment_status,
            'payment_method'      => $this->payment_method,
            'payment_reference'   => $this->payment_reference,
            'delivery_type'       => $this->delivery_type,
            'delivery_address'    => $this->delivery_address,
            'pickup_location_id'  => $this->pickup_location_id,
            'notes'               => $this->notes,
            'estimated_delivery'  => $date($this->estimated_delivery),
            'delivered_at'        => $this->delivered_at?->toISOString(),
            'created_at'          => $this->created_at?->toISOString(),

            'customer' => $this->whenLoaded('customer', fn () => [
                'id'    => $this->customer->id,
                'name'  => trim(($this->customer->first_name ?? '') . ' ' . ($this->customer->last_name ?? '')),
                'phone' => $this->customer->phone,
            ]),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) {
                $images = $item->product?->images ?? [];
                $first  = is_array($images) && count($images) ? $images[0] : null;

                return [
                    'id'              => $item->id,
                    'product_id'      => $item->product_id,
                    'product_name'    => $item->product?->name ?? 'Product',
                    'thumbnail_url'   => $first ? asset('storage/' . $first) : null,
                    'quantity'        => (int) $item->quantity,
                    'unit_price'      => (float) $item->unit_price,
                    'discount_amount' => (float) $item->discount_amount,
                    'total_price'     => (float) $item->total_price,
                ];
            })->values()),

            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id'        => $p->id,
                'amount'    => (float) $p->amount,
                'method'    => $p->method,
                'reference' => $p->reference,
                'paid_at'   => $p->paid_at?->toISOString(),
            ])->values()),
        ];
    }
}