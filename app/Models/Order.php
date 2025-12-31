<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    // Payment statuses
    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';

    // Order statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';
    protected $fillable = [
        'user_id',
        'obat_id',
        'quantity',
        'total_price',
        'status',
        'shipping_address',
        'payment_method',
        'payment_status',
        'payment_details',
    'paid_at',
    'paid_by',
    ];

    protected $casts = [
        'payment_details' => 'array',
    'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function obat()
    {
        return $this->belongsTo(Obat::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
