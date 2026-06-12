<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AFAOrders extends Model
{
    protected $table = 'afa_orders';
    protected $fillable = ['user_id','afa_product_id', 'status', 'full_name', 'ghana_card_number', 'phone','dob','occupation','region'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function afaproduct(){
        return $this->belongsTo(AFAProduct::class, 'afa_product_id');
    }
};




