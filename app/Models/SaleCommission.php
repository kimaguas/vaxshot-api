<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleCommission extends Model
{
    protected $fillable = [
        'sale_id',
        'commission_amount',
        'cost_overrides',
        'collected_at',
        'collected_by',
        'notes',
    ];

    protected $casts = [
        'collected_at'      => 'datetime',
        'commission_amount' => 'decimal:2',
        'cost_overrides'    => 'array',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
