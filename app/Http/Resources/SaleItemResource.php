<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'product_id'  => $this->product_id,
            'brand_name'  => $this->product_name ?? $this->product?->brand_name,
            'lot_number'  => $this->lot_number,
            'expiry_date' => $this->expiry_date?->format('M d, Y'),
            'quantity'    => $this->quantity,
            'unit_price'  => $this->unit_price,
            'total_price' => $this->total_price,
        ];
    }
}