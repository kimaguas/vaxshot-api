<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'received_by',
        'receipt_number',
        'receipt_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    // Auto generate Receipt number
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($receipt) {
            $latest = static::latest()->first();
            $number = $latest ? intval(substr($latest->receipt_number, 3)) + 1 : 1;
            $receipt->receipt_number = 'GR-' . str_pad($number, 5, '0', STR_PAD_LEFT);
        });
    }

    // Relationships
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class);
    }
}