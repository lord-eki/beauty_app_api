<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'day_of_week'  => $this->day_of_week,
            'day_name'     => $this->day_name,        // via getDayNameAttribute()
            'start_time'   => $this->start_time,
            'end_time'     => $this->end_time,
            'is_available' => $this->is_available,

            'location' => $this->whenLoaded('businessLocation', fn() => [
                'id'   => $this->businessLocation->id,
                'name' => $this->businessLocation->name,
                'city' => $this->businessLocation->city,
            ]),

            'staff' => $this->whenLoaded('staff', fn() => $this->staff ? [
                'id'   => $this->staff->id,
                'name' => $this->staff->name,
            ] : null),
        ];
    }
}