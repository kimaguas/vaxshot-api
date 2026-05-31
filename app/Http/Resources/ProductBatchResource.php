<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'product_id'         => $this->product_id,
            'brand_name'         => $this->product?->brand_name,
            'product_code'       => $this->product?->product_code,
            'lot_number'         => $this->lot_number,
            'expiry_date'        => $this->expiry_date?->format('M d, Y'),
            'quantity'           => $this->quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'unit_cost'          => $this->unit_cost,
            'status'             => $this->status,
            'is_expiring_soon'   => $this->isExpiringSoon(),
            'is_expired'         => $this->isExpired(),
            'created_at'         => $this->created_at->format('M d, Y'),
        ];
    }
}