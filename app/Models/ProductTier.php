<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductTier extends Model
{
    use HasFactory;

    protected $table = 'product_tiers';

    protected $fillable = [
        'catalog_id',
        'tier_label',
        'min_qty',
        'max_qty',
        'price',
        'sort_order',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'catalog_id');
    }
}
