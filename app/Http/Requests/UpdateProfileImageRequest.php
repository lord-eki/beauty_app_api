<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileImageRequest extends FormRequest
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
            'profile_image' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2MB max
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'profile_image.required' => 'Please select an image to upload.',
            'profile_image.image' => 'The file must be an image.',
            'profile_image.mimes' => 'Image must be in JPEG, JPG, PNG, or WebP format.',
            'profile_image.max' => 'Image size cannot exceed 2MB.',
        ];
    }
}
