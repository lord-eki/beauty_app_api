<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_min' => $this->price_min ? (float) $this->price_min : null,
            'price_max' => $this->price_max ? (float) $this->price_max : null,
            'duration_minutes' => $this->duration_minutes,
            'images' => $this->images ? array_map(fn($img) => asset('storage/' . $img), $this->images) : [],
            'is_home_service' => $this->is_home_service,
            'is_active' => $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'business_profile' => new BusinessProfileResource($this->whenLoaded('businessProfile')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
