<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'movement_type'  => $this->movement_type,
            'quantity'       => $this->quantity,   // signed: positive = in, negative = out
            'reference_type' => $this->reference_type,
            'reference_id'   => $this->reference_id,
            'notes'          => $this->notes,
            'created_at'     => $this->created_at?->toISOString(),

            'product' => $this->whenLoaded('productInventory', fn() => [
                'id'   => $this->productInventory->product->id   ?? null,
                'name' => $this->productInventory->product->name ?? null,
                'sku'  => $this->productInventory->product->sku  ?? null,
            ]),

            'location' => $this->whenLoaded('productInventory', fn() => [
                'id'   => $this->productInventory->businessLocation->id   ?? null,
                'name' => $this->productInventory->businessLocation->name ?? null,
                'city' => $this->productInventory->businessLocation->city ?? null,
            ]),

            'performed_by' => $this->whenLoaded('performedBy', fn() => [
                'id'         => $this->performedBy->id,
                'first_name' => $this->performedBy->first_name,
                'last_name'  => $this->performedBy->last_name,
            ]),
        ];
    }
}