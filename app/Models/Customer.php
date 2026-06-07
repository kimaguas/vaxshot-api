<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'barangay',
        'city',
        'province',
        'contact_no',
        'specialization',
        'status',
        'area_code_id',
    ];

    // Full address accessor
    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address,
            $this->barangay,
            $this->city,
            $this->province,
        ])->filter()->implode(', ');
    }

    // Relationship to Sales
   /* public function sales()
    {
        return $this->hasMany(Sale::class);
    }*/

    public function areaCode()
    {
        return $this->belongsTo(AreaCode::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}