<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'purchase_order_receipt_item_id',
        'lot_number',
        'expiry_date',
        'quantity',
        'remaining_quantity',
        'unit_cost',
        'status',
    ];

    protected $casts = [
        'expiry_date'        => 'date',
        'unit_cost'          => 'decimal:2',
        'quantity'           => 'integer',
        'remaining_quantity' => 'integer',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function receiptItem()
    {
        return $this->belongsTo(PurchaseOrderReceiptItem::class, 'purchase_order_receipt_item_id');
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('remaining_quantity', '>', 0)
                     ->where('expiry_date', '>', now());
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('status', 'active')
                     ->where('expiry_date', '<=', now()->addDays($days))
                     ->where('expiry_date', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<=', now())
                     ->where('status', 'active');
    }

    // FEFO - order by expiry date ascending
    public function scopeFEFO($query)
    {
        return $query->active()->orderBy('expiry_date', 'asc');
    }

    // Check if expiring soon
    public function isExpiringSoon($days = 30): bool
    {
        return $this->expiry_date <= now()->addDays($days) && 
               $this->expiry_date > now();
    }

    // Check if expired
    public function isExpired(): bool
    {
        return $this->expiry_date <= now();
    }
}