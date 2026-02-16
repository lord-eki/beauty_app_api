<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'brand' => $this->brand,
            'price' => (float) $this->price,
            'discounted_price' => $this->discounted_price ? (float) $this->discounted_price : null,
            'effective_price' => (float) $this->getEffectivePrice(),
            'has_discount' => $this->hasDiscount(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'stock_quantity' => $this->stock_quantity,
            'sku' => $this->sku,
            'images' => $this->images ? array_map(fn($img) => asset('storage/' . $img), $this->images) : [],
            'specifications' => $this->specifications,
            'is_active' => $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'business_profile' => new BusinessProfileResource($this->whenLoaded('businessProfile')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
