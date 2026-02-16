<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessProfileRequest extends FormRequest
{
     public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'in:services,products,both'],
            'description' => ['nullable', 'string'],
            'website' => ['nullable', 'url', 'max:500'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'facebook' => ['nullable', 'string', 'max:100'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'business_hours' => ['nullable', 'array'],
            'business_hours.*.day' => ['required_with:business_hours', 'string'],
            'business_hours.*.open' => ['nullable', 'date_format:H:i'],
            'business_hours.*.close' => ['nullable', 'date_format:H:i'],
            'business_hours.*.is_closed' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required' => 'Business name is required',
            'business_type.required' => 'Business type is required',
            'business_type.in' => 'Business type must be: services, products, or both',
        ];
    }
}
