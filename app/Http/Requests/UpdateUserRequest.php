<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->user()->id;

        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'user_type' => ['sometimes', 'string', 'in:customer,provider'],
            'email' => [
                'sometimes',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already in use by another account.',
            'phone.unique' => 'This phone number is already in use by another account.',
            'first_name.max' => 'First name cannot exceed 100 characters.',
            'last_name.max' => 'Last name cannot exceed 100 characters.',
        ];
    }
}
