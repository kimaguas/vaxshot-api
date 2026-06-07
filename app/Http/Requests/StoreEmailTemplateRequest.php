<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'category'   => 'required|in:companies,healthcare_providers,doctors,government_units,corporate,other',
            'subject'    => 'required|string|max:500',
            'body'       => 'required|string',
            'signature'  => 'required|string',
            'is_default' => 'nullable|boolean',
        ];
    }
}
