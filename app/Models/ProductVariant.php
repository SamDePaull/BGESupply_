<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'title',
        'option1_value',
        'option2_value',
        'option3_value',
        'sku',
        'price',
        'inventory_quantity',
        'shopify_variant_id',
        'shopify_inventory_item_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    protected $attributes = [
        'inventory_quantity' => 0,
    ];

    public function setInventoryQuantityAttribute($value): void
    {
        $this->attributes['inventory_quantity'] =
            ($value === null || $value === '') ? 0 : (int) $value;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
