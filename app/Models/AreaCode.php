<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AreaCode extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'commission_percentage'];

    protected $casts = [
        'commission_percentage' => 'decimal:2',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
