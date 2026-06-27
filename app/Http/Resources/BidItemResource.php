<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BidItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'bid_id'           => $this->bid_id,
            'item_description' => $this->item_description,
            'quantity'         => (int) $this->quantity,
            'unit'             => $this->unit,
            'abc_budget'       => (float) $this->abc_budget,
            'bid_price'        => (float) $this->bid_price,
            'total_bid_amount' => (float) $this->total_bid_amount,
            'total_abc_amount' => (float) $this->total_abc_amount,
        ];
    }
}
