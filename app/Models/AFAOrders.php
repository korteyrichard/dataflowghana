<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AFAOrders extends Model
{
    protected $table = 'afa_orders';
    protected $fillable = ['user_id','Afa_product_id', 'status', 'full_name', 'email', 'phone','dob','occupation','region'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function afaproduct(){
        return $this->belongsTo(AFAProduct::class, 'Afa_product_id');
    }
};




