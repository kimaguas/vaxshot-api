<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'po_number'              => $this->po_number,
            'supplier'               => $this->supplier?->company,
            'supplier_id'            => $this->supplier_id,
            'created_by'             => $this->createdBy?->name,
            'order_date'             => $this->order_date?->format('M d, Y'),
            'expected_delivery_date' => $this->expected_delivery_date?->format('M d, Y'),
            'status'                 => $this->status,
            'notes'                  => $this->notes,
            'total_amount'           => $this->total_amount,
            'items'                  => PurchaseOrderItemResource::collection(
                                         $this->whenLoaded('items')
                                       ),
            'receipts'               => PurchaseOrderReceiptResource::collection(
                                         $this->whenLoaded('receipts')
                                       ),
            'created_at'             => $this->created_at->format('M d, Y'),
        ];
    }
}