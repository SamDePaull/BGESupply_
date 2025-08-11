<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyProductRaw extends Model
{
    // Tambahkan ini supaya model pakai tabel yang benar
    protected $table = 'shopify_products_raw';

    protected $fillable = [
        'shopify_product_id',
        'payload',
        'fetched_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'fetched_at' => 'datetime',
    ];
}
