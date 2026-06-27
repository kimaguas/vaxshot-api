<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'bid_number',
        'bid_reference_no',
        'procurement_reference_no',
        'project_title',
        'agency',
        'address',
        'contact_person',
        'contact_no',
        'bid_posted_date',
        'pre_bid_date',
        'bid_deadline',
        'bid_submission_date',
        'bid_opening_date',
        'delivery_date',
        'status',
        'grand_total',
        'total_abc_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'bid_posted_date'     => 'date',
        'pre_bid_date'        => 'datetime',
        'bid_deadline'        => 'datetime',
        'bid_submission_date' => 'datetime',
        'bid_opening_date'    => 'datetime',
        'delivery_date'       => 'date',
        'grand_total'         => 'decimal:2',
        'total_abc_amount'    => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bid) {
            $bid->bid_number = 'BID-TEMP-' . uniqid();
        });

        static::created(function ($bid) {
            $bid->updateQuietly([
                'bid_number' => 'BID-' . str_pad($bid->id, 5, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function items()
    {
        return $this->hasMany(BidItem::class);
    }

    public function attachments()
    {
        return $this->hasMany(BidAttachment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
