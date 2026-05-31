<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'tin_no'         => $this->tin_no,
            'company'        => $this->company,
            'address'        => $this->address,
            'contact_person' => $this->contact_person,
            'contact_no'     => $this->contact_no,
            'status'         => $this->status,
            'created_at'     => $this->created_at->format('M d, Y'),
        ];
    }
}