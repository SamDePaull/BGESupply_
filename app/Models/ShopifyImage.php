<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyImage extends Model
{
    // Tabel non-standar
    protected $table = 'shopify_images';

    // PK = id Shopify (bigint)
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id','shopify_product_id','position','src','width','height','alt',
        'admin_graphql_api_id','shopify_created_at','shopify_updated_at',
        'variant_ids','extra',
    ];

    protected $casts = [
        'shopify_created_at' => 'datetime',
        'shopify_updated_at' => 'datetime',
        'variant_ids'        => 'array',
        'extra'              => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(ShopifyProduct::class, 'shopify_product_id');
    }
}
