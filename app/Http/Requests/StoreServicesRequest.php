<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicesRequest extends FormRequest
{
public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'gte:price_min'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_home_service' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }


    /** Older app builds sent a single `price`; map it to the range columns. */
    protected function prepareForValidation(): void
    {
        if ($this->has('price') && ! $this->has('price_min')) {
            $this->merge([
                'price_min' => $this->input('price'),
                'price_max' => $this->input('price_max', $this->input('price')),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'name.required' => 'Service name is required',
            'description.required' => 'Please describe the service (one point per line)',
            'description.min' => 'Description is too short — add a few details',
            'price_max.gte' => 'Maximum price must be greater than or equal to minimum price',
        ];
    }
}