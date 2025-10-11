<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'business_name',
        'password',
        'wallet_balance', // added wallet_balance to fillable
        'role', // added role to fillable
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance' => 'decimal:2', // cast wallet_balance as decimal
            'role' => 'string', // cast role as string
        ];
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function afaOrders()
    {
        return $this->hasMany(AFAOrders::class);
    }
    
    /**
     * Get the default role for the user.
     *
     * @return string
     */
    protected static function boot()
    {
        parent::boot();
    
        static::creating(function ($user) {
            $user->role = $user->role ?? 'customer';
        });
    }

    /**
     * Check if the user is a customer.
     *
     * @return bool
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Check if the user is an agent.
     *
     * @return bool
     */
    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    /**
     * Check if the user is an admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
