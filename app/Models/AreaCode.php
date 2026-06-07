<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AreaCode extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description'];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
