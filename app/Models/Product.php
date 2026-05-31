<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_code',
        'brand_name',
        'supplier_id',
        'description',
        'acquisition_cost',
        'selling_price',
        'stock',
        'maintaining_stock',
        'status',
    ];

    protected $casts = [
        'acquisition_cost' => 'decimal:2',
        'selling_price'    => 'decimal:2',
        'stock'            => 'integer',
        'maintaining_stock'=> 'integer',
    ];

    // Relationship to Supplier
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // Check if product is low on stock
    public function isLowStock(): bool
    {
        return $this->stock <= $this->maintaining_stock;
    }

    // Scope for active products only
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope for low stock products
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'maintaining_stock');
    }
}