<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gemini extends Model
{
    protected $table = 'gemini';

    protected $primaryKey = 'gemini_id';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'role',
        'content',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'users_id');
    }
}
