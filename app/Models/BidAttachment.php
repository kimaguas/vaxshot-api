<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidAttachment extends Model
{
    protected $fillable = [
        'bid_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function bid()
    {
        return $this->belongsTo(Bid::class);
    }
}
