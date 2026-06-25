<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleAttachment extends Model
{
    protected $fillable = [
        'sale_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
