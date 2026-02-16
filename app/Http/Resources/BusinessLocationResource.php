<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'county' => $this->county,
            'postal_code' => $this->postal_code,
            'full_address' => $this->getFullAddress(),
            'coordinates' => $this->getCoordinates(),
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active,
            'business_profile' => new BusinessProfileResource($this->whenLoaded('businessProfile')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
