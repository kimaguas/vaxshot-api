<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_number',
        'invoice_number',
        'or_number',
        'customer_id',
        'created_by',
        'sale_date',
        'payment_method',
        'payment_status',
        'total_amount',
        'amount_paid',
        'balance',
        'status',
        'notes',
    ];

    protected $casts = [
        'sale_date'    => 'date',
        'total_amount' => 'decimal:2',
        'amount_paid'  => 'decimal:2',
        'balance'      => 'decimal:2',
    ];

    // Auto generate Sale Number
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($sale) {
            $latest = static::latest()->first();
            $number = $latest ? intval(substr($latest->sale_number, 3)) + 1 : 1;
            $sale->sale_number = 'SI-' . str_pad($number, 5, '0', STR_PAD_LEFT);
        });
    }

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    // Update payment status
    public function updatePaymentStatus()
    {
        $totalPaid = $this->payments->sum('amount');
        $balance   = $this->total_amount - $totalPaid;

        $this->update([
            'amount_paid'    => $totalPaid,
            'balance'        => $balance,
            'payment_status' => $balance <= 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'unpaid'),
        ]);
    }
}