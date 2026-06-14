<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tin_no'         => 'nullable|string|max:255',
            'company'        => 'required|string|max:255|unique:suppliers,company',
            'address'        => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'contact_no'     => 'nullable|string|max:255',
            'status'         => 'in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'company.required' => 'Company name is required',
            'company.unique'   => 'A supplier with this company name already exists.',
        ];
    }
}