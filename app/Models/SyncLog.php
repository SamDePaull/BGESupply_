<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'job',
        'action',
        'product_id',
        'shopify_product_id',
        'http_status',
        'status',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
