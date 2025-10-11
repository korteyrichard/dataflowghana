<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Transaction extends Model
{
    protected $fillable = ['order_id', 'user_id', 'admin_id', 'amount', 'status', 'type', 'description', 'reference'];
    
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->created_at = Carbon::now('Africa/Accra');
            $model->updated_at = Carbon::now('Africa/Accra');
        });
        
        static::updating(function ($model) {
            $model->updated_at = Carbon::now('Africa/Accra');
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
