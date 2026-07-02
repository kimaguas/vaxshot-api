<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'username'     => $this->username,
            'email'        => $this->email,
            'role'         => $this->getRoleNames()->first(),
            'permissions'  => $this->getDirectPermissions()->pluck('name')->values(),
            'area_code_id'          => $this->area_code_id,
            'sales_rep_commission'  => $this->sales_rep_commission,
            'area_code'    => $this->whenLoaded('areaCode', fn () => [
                'id'   => $this->areaCode->id,
                'code' => $this->areaCode->code,
                'name' => $this->areaCode->name,
            ]),
            'created_at'   => $this->created_at->format('M d, Y'),
        ];
    }
}