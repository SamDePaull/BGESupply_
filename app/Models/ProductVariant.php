<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'title', 'option1_value', 'option2_value', 'option3_value', 'sku', 'barcode', 'price', 'compare_at_price', 'inventory_quantity', 'requires_shipping', 'taxable', 'weight', 'weight_unit', 'shopify_variant_id', 'shopify_inventory_item_id', 'product_image_id'];
    protected $casts = [
        'shopify_variant_id'         => 'integer',
        'shopify_inventory_item_id'  => 'integer',
        'price'                      => 'decimal:2',
        'compare_at_price'           => 'decimal:2',
        'inventory_quantity'         => 'integer',
    ];
    protected $attributes = ['inventory_quantity' => 0, 'requires_shipping' => true, 'taxable' => true];
    public function setInventoryQuantityAttribute($v)
    {
        $this->attributes['inventory_quantity'] = ($v === null || $v === '') ? 0 : (int)$v;
    }
    public function setPriceAttribute($v)
    {
        $this->attributes['price'] = ($v === '') ? null : $v;
    }
    public function setCompareAtPriceAttribute($v)
    {
        $this->attributes['compare_at_price'] = ($v === '') ? null : $v;
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function image()
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }
}
