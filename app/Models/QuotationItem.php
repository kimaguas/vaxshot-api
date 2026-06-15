<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'product_id',
        'product_name',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'expiry_date',
        'use_flat_price',
    ];

    protected $casts = [
        'unit_price'    => 'decimal:2',
        'total_price'   => 'decimal:2',
        'quantity'      => 'integer',
        'expiry_date'   => 'date',
        'use_flat_price'=> 'boolean',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
