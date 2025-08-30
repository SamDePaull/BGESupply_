<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyProductStaging extends Model
{
    protected $table = 'shopify_product_staging';
    protected $guarded = [];
    protected $casts = [
        'payload' => 'array',
    ];
}
