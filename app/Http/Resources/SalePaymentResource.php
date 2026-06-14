<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'sale_id'          => $this->sale_id,
            'or_number'        => $this->or_number,
            'amount'           => $this->amount,
            'payment_method'   => $this->payment_method,
            'payment_date'     => $this->payment_date?->format('M d, Y'),
            'reference_number' => $this->reference_number,
            'received_by'      => $this->receivedBy?->name,
            'notes'               => $this->notes,
            'or_attachment_url'   => $this->or_attachment
                ? asset('storage/' . $this->or_attachment)
                : null,
            'created_at'          => $this->created_at->format('M d, Y'),
        ];
    }
}