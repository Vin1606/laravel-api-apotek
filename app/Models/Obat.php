<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Obat extends Model
{
    protected $table = 'obats';

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'image',
    ];
}
