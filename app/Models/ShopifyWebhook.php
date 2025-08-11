<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyWebhook extends Model
{
    protected $fillable = [
        'event_type','shopify_id','payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
