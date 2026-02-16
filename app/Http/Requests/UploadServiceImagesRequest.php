<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadServiceImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'At least one image is required',
            'images.max' => 'You can upload maximum 5 images',
            'images.*.image' => 'Each file must be an image',
            'images.*.mimes' => 'Images must be: jpeg, jpg, png, or webp',
            'images.*.max' => 'Each image must not exceed 2MB',
        ];
    }
}