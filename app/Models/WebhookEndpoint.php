<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    protected $fillable = ['user_id', 'url', 'secret', 'active'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
