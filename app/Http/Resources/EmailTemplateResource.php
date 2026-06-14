<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'category'   => $this->category,
            'subject'    => $this->subject,
            'body'       => $this->body,
            'signature'  => $this->signature,
            'is_default'  => $this->is_default,
            'header_html' => $this->header_html,
            'created_at'  => $this->created_at->format('Y-m-d'),
        ];
    }
}
