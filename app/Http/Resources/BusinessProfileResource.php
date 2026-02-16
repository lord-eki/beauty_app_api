<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\Resources\BusinessLocationResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'description' => $this->description,
            'website' => $this->website,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,
            'whatsapp' => $this->whatsapp,
            'business_hours' => $this->business_hours,
            'average_rating' => (float) $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'is_verified' => $this->is_verified,
            'user' => new UserResource($this->whenLoaded('user')),
            'locations' => BusinessLocationResource::collection($this->whenLoaded('locations')),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    } 
}
