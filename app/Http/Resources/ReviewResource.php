<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'rating'      => $this->rating,
            'comment'     => $this->comment,
            'images'      => collect($this->images ?? [])
                ->map(fn($img) => asset('storage/' . $img))
                ->values(),
            'is_verified' => $this->is_verified,
            'service_id'  => $this->service_id,
            'product_id'  => $this->product_id,
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),

            'user' => $this->whenLoaded('user', fn() => [
                'id'            => $this->user->id,
                'first_name'    => $this->user->first_name,
                'last_name'     => $this->user->last_name,
                'profile_image' => $this->user->profile_image
                    ? asset('storage/' . $this->user->profile_image)
                    : null,
            ]),

            'service' => $this->whenLoaded('service', fn() => [
                'id'   => $this->service->id,
                'name' => $this->service->name,
            ]),

            'product' => $this->whenLoaded('product', fn() => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
            ]),
        ];
    }
}