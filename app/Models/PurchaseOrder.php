<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'created_by',
        'order_date',
        'expected_delivery_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'order_date'              => 'date',
        'expected_delivery_date'  => 'date',
    ];

    // Auto generate PO number
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($po) {
            $latest = static::latest()->first();
            $number = $latest ? intval(substr($latest->po_number, 3)) + 1 : 1;
            $po->po_number = 'PO-' . str_pad($number, 5, '0', STR_PAD_LEFT);
        });
    }

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts()
    {
        return $this->hasMany(PurchaseOrderReceipt::class);
    }

    // Total amount accessor
    public function getTotalAmountAttribute()
    {
        return $this->items->sum('total_cost');
    }
}