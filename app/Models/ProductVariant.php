<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'price',
        'quantity',
        'status',
        'variant_attributes'
    ];

    protected $casts = [
        'variant_attributes' => 'array'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class, 'product_variant_id');
    }

    // Get formatted variant name (e.g., "Red - Large")
    public function getVariantNameAttribute()
    {
        if (empty($this->variant_attributes)) {
            return 'Standard';
        }

        return collect($this->variant_attributes)->values()->implode(' - ');
    }

    // Get full product name with variant (e.g., "T-Shirt - Red - Large")
    public function getFullNameAttribute()
    {
        if (empty($this->variant_attributes)) {
            return $this->product->name;
        }
        return $this->product->name . ' - ' . $this->variant_name;
    }
}