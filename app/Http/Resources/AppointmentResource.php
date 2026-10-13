<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
 public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id'          => $this->customer_id,
            'business_profile_id'  => $this->business_profile_id,
            'business_location_id' => $this->business_location_id,
            'service_id'           => $this->service_id,
            'staff_id'             => $this->staff_id,
            'business_name'        => $this->whenLoaded('businessProfile', fn () => $this->businessProfile?->business_name),
            'service_name'         => $this->whenLoaded('service', fn () => $this->service?->name),
            'staff_name'           => $this->whenLoaded('staff', fn () => $this->staff?->name),
            'customer_name'        => $this->whenLoaded('customer', fn () => trim(($this->customer?->first_name ?? '') . ' ' . ($this->customer?->last_name ?? ''))),
            'customer_phone'       => $this->whenLoaded('customer', fn () => $this->customer?->phone),
            'cancellation_reason'  => $this->cancellation_reason,
            'appointment_date' => $this->appointment_date->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'customer_notes' => $this->customer_notes,
            'business_notes' => $this->business_notes,
            'total_amount' => (float) $this->total_amount,
            'payment_status' => $this->payment_status,
            'can_cancel' => $this->canBeCancelled(),
            'customer' => new UserResource($this->whenLoaded('customer')),
            'business' => new BusinessProfileResource($this->whenLoaded('businessProfile')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'staff' => $this->whenLoaded('staff', function() {
                return [
                    'id' => $this->staff->id,
                    'name' => $this->staff->name,
                ];
            }),
            'location' => new BusinessLocationResource($this->whenLoaded('businessLocation')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}