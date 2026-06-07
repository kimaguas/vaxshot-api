<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'supplier_id'    => $this->supplier_id,
            'supplier'       => $this->whenLoaded('supplier', fn() => [
                'id'      => $this->supplier->id,
                'company' => $this->supplier->company,
            ]),
            'brand_name'       => $this->brand_name,
            'lot_no'           => $this->lot_no,
            'generic_name'     => $this->generic_name,
            'acquisition_cost' => $this->acquisition_cost ? (float) $this->acquisition_cost : null,
            'indication'       => $this->indication,
            'expiry_date'    => $this->expiry_date?->format('Y-m-d'),
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'notes'          => $this->notes,
            'status'         => $this->status,
            'tiers'          => $this->whenLoaded('tiers', fn() =>
                $this->tiers->map(fn($tier) => [
                    'id'         => $tier->id,
                    'tier_label' => $tier->tier_label,
                    'min_qty'    => $tier->min_qty,
                    'max_qty'    => $tier->max_qty,
                    'price'      => (float) $tier->price,
                    'sort_order' => $tier->sort_order,
                ])
            ),
            'created_at'     => $this->created_at->format('M d, Y'),
        ];
    }
}
