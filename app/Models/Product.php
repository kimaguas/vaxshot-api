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
        'acquisition_cost'  => 'decimal:2',
        'selling_price'     => 'decimal:2',
        'stock'             => 'integer',
        'maintaining_stock' => 'integer',
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function activeBatches()
    {
        return $this->hasMany(ProductBatch::class)
                    ->where('status', 'active')
                    ->where('remaining_quantity', '>', 0)
                    ->where('expiry_date', '>', now())
                    ->orderBy('expiry_date', 'asc');
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    // Get total stock from all active batches
    public function getTotalStockAttribute(): int
    {
        return $this->activeBatches->sum('remaining_quantity');
    }

    // Check if low stock
    public function isLowStock(): bool
    {
        return $this->stock <= $this->maintaining_stock;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'maintaining_stock');
    }
}