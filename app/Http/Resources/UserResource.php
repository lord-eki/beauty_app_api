<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
      return [
        'id' => $this->id,
            'uuid' => $this->uuid,
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->first_name . ' ' . $this->last_name,
            'profile_image' => $this->profile_image ? asset('storage/' . $this->profile_image) : null,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
            'email_verified' => !is_null($this->email_verified_at),
            'phone_verified' => !is_null($this->phone_verified_at),
            'created_at' => $this->created_at?->toIso8601String(),
            'last_active_at' => $this->last_active_at?->toIso8601String(),
            
            // Include business profile if user is a provider
            'business_profile' => $this->when(
                $this->user_type === 'provider' && $this->businessProfile,
                fn() => new BusinessProfileResource($this->businessProfile))
      ];
    }
}
