<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\BidAttachmentResource;

class BidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'bid_number'               => $this->bid_number,
            'bid_reference_no'         => $this->bid_reference_no,
            'procurement_reference_no' => $this->procurement_reference_no,
            'project_title'            => $this->project_title,
            'agency'                   => $this->agency,
            'address'                  => $this->address,
            'contact_person'           => $this->contact_person,
            'contact_no'               => $this->contact_no,
            'bid_posted_date'          => $this->bid_posted_date?->format('Y-m-d'),
            'pre_bid_date'             => $this->pre_bid_date?->format('Y-m-d\TH:i'),
            'bid_deadline'             => $this->bid_deadline?->format('Y-m-d\TH:i'),
            'bid_submission_date'      => $this->bid_submission_date?->format('Y-m-d\TH:i'),
            'bid_opening_date'         => $this->bid_opening_date?->format('Y-m-d\TH:i'),
            'delivery_date'            => $this->delivery_date?->format('Y-m-d'),
            'bid_posted_date_fmt'      => $this->bid_posted_date?->format('M d, Y'),
            'pre_bid_date_fmt'         => $this->pre_bid_date?->format('M d, Y h:i A'),
            'bid_deadline_fmt'         => $this->bid_deadline?->format('M d, Y h:i A'),
            'bid_submission_date_fmt'  => $this->bid_submission_date?->format('M d, Y h:i A'),
            'bid_opening_date_fmt'     => $this->bid_opening_date?->format('M d, Y h:i A'),
            'delivery_date_fmt'        => $this->delivery_date?->format('M d, Y'),
            'status'                   => $this->status,
            'grand_total'              => (float) $this->grand_total,
            'total_abc_amount'         => (float) $this->total_abc_amount,
            'notes'                    => $this->notes,
            'created_by'               => $this->createdBy?->name,
            'items'                    => BidItemResource::collection($this->whenLoaded('items')),
            'attachments'              => BidAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at'               => $this->created_at->format('M d, Y'),
        ];
    }
}
