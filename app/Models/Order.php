<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'source', 'order_number', 'total_price', 'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];
}
