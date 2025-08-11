<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyVariant extends Model
{
    // PK = id Shopify (bigint)
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id','shopify_product_id','title','price','position','inventory_policy',
        'compare_at_price','option1','option2','option3',
        'shopify_created_at','shopify_updated_at','taxable','barcode',
        'fulfillment_service','grams','inventory_management','requires_shipping',
        'sku','weight','weight_unit','inventory_item_id','inventory_quantity',
        'old_inventory_quantity','admin_graphql_api_id','image_id','extra',
    ];

    protected $casts = [
        'shopify_created_at' => 'datetime',
        'shopify_updated_at' => 'datetime',
        'taxable'            => 'boolean',
        'requires_shipping'  => 'boolean',
        'extra'              => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(ShopifyProduct::class, 'shopify_product_id');
    }
}
