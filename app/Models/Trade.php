<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    protected $fillable = [
        'symbol','buy_order_id','sell_order_id','buyer_id','seller_id',
        'price','amount','usd_value','commission',
    ];
}
