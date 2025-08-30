<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['title', 'handle', 'description', 'sku', 'price', 'compare_at_price', 'cost_price', 'inventory_quantity', 'vendor', 'product_type', 'category_id', 'tags', 'options_schema', 'option1_name', 'option2_name', 'option3_name', 'requires_shipping', 'taxable', 'weight', 'weight_unit', 'status', 'published_at', 'seo_title', 'seo_description', 'shopify_product_id', 'sync_status', 'last_synced_at', 'last_error', 'shopify_updated_at'];
    protected $casts = ['options_schema' => 'array', 'last_synced_at' => 'datetime', 'shopify_updated_at' => 'datetime', 'published_at' => 'datetime', 'requires_shipping' => 'boolean', 'taxable' => 'boolean', 'price' => 'decimal:2', 'compare_at_price' => 'decimal:2', 'cost_price' => 'decimal:2'];
    protected $attributes = ['inventory_quantity' => 0, 'status' => 'draft', 'requires_shipping' => true, 'taxable' => true];
    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if ($p->inventory_quantity === null || $p->inventory_quantity === '') {
                $p->inventory_quantity = 0;
            }
        });
        static::saved(function (Product $p) {
            $p->applyVariantPriceFallback();
        });
    }
    public function applyVariantPriceFallback(): void
    {
        $baseP = $this->price;
        $baseC = $this->compare_at_price;
        $vars = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();
        foreach ($vars as $v) {
            $dirty = false;
            if ((is_null($v->price) || $v->price === '') && !is_null($baseP)) {
                $v->price = $baseP;
                $dirty = true;
            }
            if ((is_null($v->compare_at_price) || $v->compare_at_price === '') && !is_null($baseC)) {
                $v->compare_at_price = $baseC;
                $dirty = true;
            }
            if ($v->inventory_quantity === null || $v->inventory_quantity === '') {
                $v->inventory_quantity = 0;
                $dirty = true;
            }
            if ($dirty) $v->saveQuietly();
        }
    }
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderByRaw('COALESCE(position,9999) asc')->orderBy('id');
    }
}
