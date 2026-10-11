<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Ownership is verified in ProductController::update()
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
            'category_id'      => ['sometimes', 'exists:categories,id'],
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'description'      => ['sometimes', 'required', 'string', 'min:10', 'max:3000'],
            'brand'            => ['nullable', 'string', 'max:100'],
            'price'            => ['sometimes', 'required', 'numeric', 'min:0'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity'   => ['sometimes', 'required', 'integer', 'min:0'],
            'sku'              => ['nullable', 'string', 'max:100'],
            'specifications'   => ['nullable', 'array'],
            'is_active'        => ['boolean'],
        ];
    }
}