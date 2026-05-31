<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'product_code'     => $this->product_code,
            'brand_name'       => $this->brand_name,
            'supplier'         => $this->supplier?->name,
            'supplier_id'      => $this->supplier_id,
            'description'      => $this->description,
            'acquisition_cost' => $this->acquisition_cost,
            'selling_price'    => $this->selling_price,
            'stock'            => $this->stock,
            'maintaining_stock'=> $this->maintaining_stock,
            'is_low_stock'     => $this->isLowStock(),
            'status'           => $this->status,
            'created_at'       => $this->created_at->format('M d, Y'),
        ];
    }
}