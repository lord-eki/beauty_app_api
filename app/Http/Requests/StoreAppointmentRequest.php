<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
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
            'service_id'           => ['required', 'exists:services,id'],
            'staff_id'             => ['nullable', 'exists:service_staff,id'],
            'business_location_id' => ['nullable', 'exists:business_locations,id'],
            'appointment_date'     => ['required', 'date', 'after_or_equal:today'],
            'start_time'           => ['required', 'date_format:H:i'],
            'customer_notes'       => ['nullable', 'string', 'max:1000'],
        ];
    }
}