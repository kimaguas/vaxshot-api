<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'supplier_id',
        'brand_name',
        'lot_no',
        'generic_name',
        'acquisition_cost',
        'indication',
        'expiry_date',
        'effective_date',
        'notes',
        'status',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiry_date'    => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function tiers()
    {
        return $this->hasMany(ProductTier::class, 'catalog_id')->orderBy('sort_order');
    }
}
