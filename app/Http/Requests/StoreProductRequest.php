<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'      => 'required|exists:suppliers,id',
            'brand_name'       => 'required|string|max:255',
            'lot_no'           => 'nullable|string|max:100',
            'generic_name'     => 'nullable|string|max:255',
            'acquisition_cost' => 'nullable|numeric|min:0',
            'indication'       => 'nullable|string|max:500',
            'expiry_date'      => 'nullable|date',
            'effective_date'   => 'nullable|date',
            'notes'            => 'nullable|string',
            'status'           => 'in:active,inactive',
            'tiers'            => 'required|array|min:1',
            'tiers.*.tier_label' => 'nullable|string|max:100',
            'tiers.*.min_qty'    => 'required|integer|min:1',
            'tiers.*.max_qty'    => 'nullable|integer|min:1',
            'tiers.*.price'      => 'required|numeric|min:0',
            'tiers.*.sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier is required',
            'supplier_id.exists'   => 'Selected supplier does not exist',
            'brand_name.required'  => 'Brand name is required',
            'tiers.required'       => 'At least one price tier is required',
            'tiers.min'            => 'At least one price tier is required',
        ];
    }
}
