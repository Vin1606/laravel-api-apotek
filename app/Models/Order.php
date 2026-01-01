<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $primaryKey = 'orders_id';
    protected $table = 'orders';
    protected $fillable = [
        'users_id',
        'total_price',
        'shipping_address',
        'notes',
        'payment_method',
        'paid_at',
        'confirmation_by',
        'payment_status',
        'image_payment',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function users()
    {
        return $this->belongsTo(User::class, 'users_id', 'users_id');
    }

    public function confirmationBy()
    {
        return $this->belongsTo(User::class, 'confirmation_by', 'users_id');
    }

    // public function orderItems()
    // {
    //     return $this->hasMany(Order_Items::class, 'orders_id', 'orders_id');
    // }

    public function items()
    {
        return $this->hasMany(Order_Items::class, 'orders_id', 'orders_id');
    }
}
