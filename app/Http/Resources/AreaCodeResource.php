<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AreaCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'description' => $this->description,
            'customers_count' => $this->whenCounted('customers'),
            'created_at'  => $this->created_at->format('M d, Y'),
        ];
    }
}
