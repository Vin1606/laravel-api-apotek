<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order_Items extends Model
{
    protected $primaryKey = 'order_items_id';
    protected $table = 'order_items';
    protected $fillable = [
        'orders_id',
        'obats_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'orders_id', 'orders_id');
    }

    public function obats()
    {
        return $this->belongsTo(Obat::class, 'obats_id', 'obats_id');
    }
}
