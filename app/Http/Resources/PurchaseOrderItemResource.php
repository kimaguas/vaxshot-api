<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'product_id'         => $this->product_id,
            'product_code'       => $this->product?->product_code,
            'brand_name'         => $this->product?->brand_name,
            'quantity_ordered'   => $this->quantity_ordered,
            'quantity_received'  => $this->quantity_received,
            'remaining_quantity' => $this->remaining_quantity,
            'unit_cost'          => $this->unit_cost,
            'total_cost'         => $this->total_cost,
            'is_fully_received'  => $this->isFullyReceived(),
        ];
    }
}