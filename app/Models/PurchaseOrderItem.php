<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'unit_cost'         => 'decimal:2',
        'total_cost'        => 'decimal:2',
        'quantity_ordered'  => 'integer',
        'quantity_received' => 'integer',
    ];

    // Relationships
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function receiptItems()
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class);
    }

    // Check if fully received
    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    // Get remaining quantity to receive
    public function getRemainingQuantityAttribute(): int
    {
        return $this->quantity_ordered - $this->quantity_received;
    }
}