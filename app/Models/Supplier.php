<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'tin_no',
        'company',
        'address',
        'contact_person',
        'contact_no',
        'status',
    ];

    // Relationship to Products
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}