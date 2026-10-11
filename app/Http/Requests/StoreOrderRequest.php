<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'delivery_type'        => ['required', 'in:pickup,delivery'],
            'delivery_address'     => ['required_if:delivery_type,delivery', 'nullable', 'array'],
            'delivery_address.address' => ['required_if:delivery_type,delivery', 'nullable', 'string', 'max:500'],
            'delivery_address.phone'   => ['nullable', 'string', 'max:20'],
            'pickup_location_id'   => ['nullable', 'exists:business_locations,id'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ];
    }
}