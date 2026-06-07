<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name'      => 'required|string|max:255',
            'contact_name'       => 'nullable|string|max:255',
            'address'            => 'nullable|string|max:500',
            'emails'             => 'required|array|min:1',
            'emails.*'           => 'required|email|max:255',
            'cc_emails'          => 'nullable|array',
            'cc_emails.*'        => 'email|max:255',
            'quotation_date'     => 'required|date',
            'valid_until'        => 'nullable|date|after_or_equal:quotation_date',
            'notes'              => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.description'  => 'nullable|string|max:500',
            'items.*.expiry_date'  => 'nullable|date',
        ];
    }
}
