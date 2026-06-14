<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_delivery_id',
        'sale_item_id',
        'quantity_delivered',
    ];

    protected $casts = [
        'quantity_delivered' => 'integer',
    ];

    public function delivery()
    {
        return $this->belongsTo(SaleDelivery::class, 'sale_delivery_id');
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
}
