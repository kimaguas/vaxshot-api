<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'subject',
        'body',
        'signature',
        'is_default',
        'header_html',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}
