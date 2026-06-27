<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BidItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bid_id',
        'item_description',
        'quantity',
        'unit',
        'abc_budget',
        'bid_price',
        'total_bid_amount',
        'total_abc_amount',
    ];

    protected $casts = [
        'quantity'         => 'integer',
        'abc_budget'       => 'decimal:2',
        'bid_price'        => 'decimal:2',
        'total_bid_amount' => 'decimal:2',
        'total_abc_amount' => 'decimal:2',
    ];

    public function bid()
    {
        return $this->belongsTo(Bid::class);
    }
}
