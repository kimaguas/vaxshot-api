<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'product'        => $this->product?->brand_name,
            'product_code'   => $this->product?->product_code,
            'batch'          => $this->batch?->lot_number,
            'expiry_date'    => $this->batch?->expiry_date?->format('M d, Y'),
            'type'           => $this->type,
            'quantity'       => $this->quantity,
            'previous_stock' => $this->previous_stock,
            'new_stock'      => $this->new_stock,
            'reference'      => $this->reference,
            'remarks'        => $this->remarks,
            'created_by'     => $this->createdBy?->name,
            'created_at'     => $this->created_at->format('M d, Y h:i A'),
        ];
    }
}