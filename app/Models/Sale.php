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
        'delivery_status',
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

        // Satisfy the NOT NULL constraint with a temporary unique value
        static::creating(function ($sale) {
            $sale->sale_number = 'SN-TEMP-' . uniqid();
        });

        // Replace with the real number once we have the auto-increment ID
        static::created(function ($sale) {
            $sale->updateQuietly([
                'sale_number' => 'SN-' . str_pad($sale->id, 5, '0', STR_PAD_LEFT),
            ]);
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

    public function deliveries()
    {
        return $this->hasMany(SaleDelivery::class);
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