<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'delivered_by',
        'delivery_number',
        'delivery_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($delivery) {
            $latest = static::latest()->first();
            $number = $latest ? intval(substr($latest->delivery_number, 3)) + 1 : 1;
            $delivery->delivery_number = 'DR-' . str_pad($number, 5, '0', STR_PAD_LEFT);
        });
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function items()
    {
        return $this->hasMany(SaleDeliveryItem::class);
    }
}
