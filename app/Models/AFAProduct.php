<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AFAProduct extends Model
{
    protected $table = 'afa_products';
    protected $fillable = ['name', 'status', 'price'];

    public function afaorders()
    {
        return $this->hasMany(AFAOrders::class, 'Afa_product_id');
    }

}
