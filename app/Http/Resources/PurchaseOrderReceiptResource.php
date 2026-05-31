<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'receipt_number' => $this->receipt_number,
            'purchase_order' => $this->purchaseOrder?->po_number,
            'received_by'    => $this->receivedBy?->name,
            'receipt_date'   => $this->receipt_date?->format('M d, Y'),
            'status'         => $this->status,
            'notes'          => $this->notes,
            'items'          => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id'                => $item->id,
                        'product_id'        => $item->product_id,
                        'product_code'      => $item->product?->product_code,
                        'brand_name'        => $item->product?->brand_name,
                        'lot_number'        => $item->lot_number,
                        'expiry_date'       => $item->expiry_date?->format('M d, Y'),
                        'quantity_received' => $item->quantity_received,
                        'unit_cost'         => $item->unit_cost,
                    ];
                });
            }),
            'created_at'     => $this->created_at->format('M d, Y'),
        ];
    }
}