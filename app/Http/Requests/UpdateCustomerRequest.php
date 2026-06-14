<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'    => ['nullable', 'string', 'max:50', Rule::unique('customers', 'customer_id')->ignore($this->route('customer'))],
            'name'           => 'sometimes|string|max:255',
            'address'        => 'nullable|string|max:255',
            'barangay'       => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:255',
            'province'       => 'nullable|string|max:255',
            'contact_no'     => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'status'         => 'sometimes|in:active,inactive',
            'area_code_id'   => 'nullable|exists:area_codes,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required',
        ];
    }
}