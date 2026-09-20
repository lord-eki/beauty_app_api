<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'quantity_available'  => $this->quantity_available,
            'quantity_reserved'   => $this->quantity_reserved,
            'net_available'       => $this->netAvailable(),
            'minimum_stock_level' => $this->minimum_stock_level,
            'maximum_stock_level' => $this->maximum_stock_level,
            'cost_price'          => $this->cost_price !== null ? (float) $this->cost_price : null,
            'is_low_stock'        => $this->isLowStock(),
            'last_restocked_at'   => $this->last_restocked_at?->toISOString(),

            'product' => $this->whenLoaded('product', fn () => [
                'id'               => $this->product->id,
                'name'             => $this->product->name,
                'sku'              => $this->product->sku,
                'price'            => (float) $this->product->price,
                'discounted_price' => $this->product->discounted_price !== null
                    ? (float) $this->product->discounted_price
                    : null,
                'images' => $this->product->images
                    ? array_map(fn ($img) => asset('storage/' . $img), $this->product->images)
                    : [],
                'is_active' => $this->product->is_active,
            ]),

            'location' => $this->whenLoaded('businessLocation', fn () => [
                'id'         => $this->businessLocation->id,
                'name'       => $this->businessLocation->name,
                'city'       => $this->businessLocation->city,
                'is_primary' => $this->businessLocation->is_primary ?? null,
            ]),
        ];
    }
}
