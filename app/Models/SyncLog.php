<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = ['job', 'action', 'product_id', 'variant_id', 'shopify_product_id', 'shopify_variant_id', 'http_status', 'status', 'message', 'body'];
    protected $casts = ['body' => 'array'];
}
