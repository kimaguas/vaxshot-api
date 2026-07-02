<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\SaleAttachmentResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'sale_number'    => $this->sale_number,
            'invoice_number' => $this->invoice_number,
            'po_number'      => $this->po_number,
            'or_number'      => $this->or_number,
            'customer'       => $this->customer?->name,
            'customer_id'    => $this->customer_id,
            'area_code_id'   => $this->area_code_id,
            'area_code'      => $this->areaCode ? [
                'id'   => $this->areaCode->id,
                'code' => $this->areaCode->code,
                'name' => $this->areaCode->name,
            ] : null,
            'created_by'     => $this->createdBy?->name,
            'sale_date'      => $this->sale_date?->format('M d, Y'),
            'payment_method' => $this->payment_method,
            'payment_status'   => $this->payment_status,
            'delivery_status'  => $this->delivery_status,
            'total_amount'   => $this->total_amount,
            'amount_paid'    => $this->amount_paid,
            'balance'        => $this->balance,
            'status'         => $this->status,
            'notes'          => $this->notes,
            'items'          => SaleItemResource::collection(
                                    $this->whenLoaded('items')
                                ),
            'payments'       => SalePaymentResource::collection(
                                    $this->whenLoaded('payments')
                                ),
            'attachments'    => SaleAttachmentResource::collection(
                                    $this->whenLoaded('attachments')
                                ),
            'created_at'     => $this->created_at->format('M d, Y'),
        ];
    }
}