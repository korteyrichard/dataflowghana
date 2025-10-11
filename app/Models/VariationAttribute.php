<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariationAttribute extends Model
{
    protected $fillable = [
        'name',
        'values'
    ];

    protected $casts = [
        'values' => 'array'
    ];
}