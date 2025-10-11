<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 
        'description', 
        'network', 
        'product_type',
        'expiry',
        'has_variants'
    ];

    protected $casts = [
        'has_variants' => 'boolean'
    ];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_product')
            ->withPivot('quantity', 'price', 'beneficiary_number', 'product_variant_id')
            ->withTimestamps();
    }

    // Get the first available variant (for simple products)
    public function firstVariant()
    {
        return $this->variants()->first();
    }

    // Get the price range for variable products
    public function getPriceRange()
    {
        $variants = $this->variants;
        if ($variants->isEmpty()) {
            return null;
        }

        $minPrice = $variants->min('price');
        $maxPrice = $variants->max('price');

        if ($minPrice == $maxPrice) {
            return number_format($minPrice, 2);
        }

        return number_format($minPrice, 2) . ' - ' . number_format($maxPrice, 2);
    }

    // Check if product is in stock
    public function isInStock()
    {
        return $this->variants()->where('status', 'IN STOCK')->where('quantity', '>', 0)->exists();
    }
}

