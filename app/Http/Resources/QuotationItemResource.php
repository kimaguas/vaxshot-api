<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'product_id'   => $this->product_id,
            'product_name' => $this->product_name,
            'supplier_name' => $this->relationLoaded('product')
                ? $this->product?->supplier?->company
                : null,
            'description'  => $this->description
                ?: ($this->relationLoaded('product') ? $this->product?->indication : null),
            'quantity'     => $this->quantity,
            'unit_price'   => $this->unit_price,
            'total_price'  => $this->total_price,
            'expiry_date'  => $this->expiry_date?->format('Y-m-d')
                ?: ($this->relationLoaded('product') ? $this->product?->expiry_date?->format('Y-m-d') : null),
            'tiers'        => $this->whenLoaded('product', function () {
                return $this->product->tiers->map(fn($t) => [
                    'tier_label' => $t->tier_label,
                    'min_qty'    => $t->min_qty,
                    'max_qty'    => $t->max_qty,
                    'price'      => $t->price,
                ]);
            }, []),
        ];
    }
}
