<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => 'sometimes|string|max:255',
            'address'        => 'nullable|string|max:255',
            'barangay'       => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:255',
            'province'       => 'nullable|string|max:255',
            'contact_no'     => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'status'         => 'sometimes|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required',
        ];
    }
}