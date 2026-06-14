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
            'customer_id'    => $this->customer_id,
            'name'           => $this->name,
            'address'        => $this->address,
            'barangay'       => $this->barangay,
            'city'           => $this->city,
            'province'       => $this->province,
            'full_address'   => $this->full_address,
            'contact_no'     => $this->contact_no,
            'specialization' => $this->specialization,
            'status'         => $this->status,
            'area_code_id'   => $this->area_code_id,
            'area_code'      => $this->whenLoaded('areaCode', fn() => [
                'id'   => $this->areaCode->id,
                'code' => $this->areaCode->code,
                'name' => $this->areaCode->name,
            ]),
            'created_at'     => $this->created_at->format('M d, Y'),
        ];
    }
}