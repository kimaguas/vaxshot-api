<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'lot_number',
        'expiry_date',
        'quantity_received',
        'unit_cost',
    ];

    protected $casts = [
        'expiry_date'       => 'date',
        'unit_cost'         => 'decimal:2',
        'quantity_received' => 'integer',
    ];

    // Relationships
    public function receipt()
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'purchase_order_receipt_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productBatch()
    {
        return $this->hasOne(ProductBatch::class);
    }
}