<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
             'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discounted_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'max:100'],
            'specifications' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages() : array
    {
        return [
             'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'name.required' => 'Product name is required',
            'price.required' => 'Price is required',
            'discounted_price.lte' => 'Discounted price must be less than or equal to the regular price',
        ];
    }
}
