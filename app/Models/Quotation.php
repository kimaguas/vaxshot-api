<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_number',
        'created_by',
        'customer_name',
        'contact_name',
        'address',
        'email',
        'emails',
        'cc_emails',
        'quotation_date',
        'valid_until',
        'total_amount',
        'status',
        'quotation_type',
        'notes',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until'    => 'date',
        'total_amount'   => 'decimal:2',
        'emails'         => 'array',
        'cc_emails'      => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quotation) {
            $quotation->quotation_number = 'QT-TEMP-' . uniqid();
        });

        static::created(function ($quotation) {
            $quotation->updateQuietly([
                'quotation_number' => 'QT-' . str_pad($quotation->id, 5, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }
}
