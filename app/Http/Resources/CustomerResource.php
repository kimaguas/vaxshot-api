<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'address'        => $this->address,
            'barangay'       => $this->barangay,
            'city'           => $this->city,
            'province'       => $this->province,
            'full_address'   => $this->full_address,
            'contact_no'     => $this->contact_no,
            'specialization' => $this->specialization,
            'status'         => $this->status,
            'created_at'     => $this->created_at->format('M d, Y'),
        ];
    }
}