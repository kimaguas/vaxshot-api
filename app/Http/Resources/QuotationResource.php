<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'quotation_number' => $this->quotation_number,
            'created_by'       => $this->createdBy?->name,
            'customer_name'    => $this->customer_name,
            'contact_name'     => $this->contact_name,
            'address'          => $this->address,
            'email'            => $this->email,
            'emails'           => $this->emails ?? ($this->email ? [$this->email] : []),
            'cc_emails'        => $this->cc_emails ?? [],
            'quotation_date'   => $this->quotation_date?->format('Y-m-d'),
            'total_amount'     => $this->total_amount,
            'status'           => $this->status,
            'quotation_type'   => $this->quotation_type ?? 'pricing',
            'notes'            => $this->notes,
            'items'            => QuotationItemResource::collection($this->whenLoaded('items')),
            'items_count'      => $this->items_count ?? $this->whenLoaded('items', fn() => $this->items->count()),
            'created_at'       => $this->created_at->format('Y-m-d'),
        ];
    }
}
