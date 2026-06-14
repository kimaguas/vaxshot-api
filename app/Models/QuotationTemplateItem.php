<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationTemplateItem extends Model
{
    protected $fillable = [
        'quotation_template_id',
        'product_id',
        'quantity',
        'unit_price',
        'description',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
