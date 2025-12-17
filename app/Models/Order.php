<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id','symbol','side','price','amount','remaining_amount','status',
        'cancelled_at','filled_at',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'filled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }
}
