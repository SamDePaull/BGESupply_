<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    // PK = id Shopify (bigint)
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id','title','vendor','handle','body_html','status',
        'product_type','template_suffix','tags','published_at',
        'admin_graphql_api_id','options','metafields','image_url','extra',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'options'      => 'array',
        'metafields'   => 'array',
        'extra'        => 'array',
    ];

    public function variants()
    {
        return $this->hasMany(ShopifyVariant::class, 'shopify_product_id');
    }

    public function images()
    {
        return $this->hasMany(ShopifyImage::class, 'shopify_product_id');
    }
}
