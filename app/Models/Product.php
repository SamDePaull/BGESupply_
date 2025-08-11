<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name','sku','price','cost_price','stock','image_url',
        'origin','origin_id','shopify_product_id','is_from_shopify','sync_status',
    ];

    // Contoh relasi ke item penjualan (POS)
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    // Helper: asal data ringkas
    public function getOriginLabelAttribute(): string
    {
        return $this->origin === 'shopify' ? 'Shopify' : 'Offline';
    }

    public function shopifyProduct()
    {
        return $this->belongsTo(\App\Models\ShopifyProduct::class, 'shopify_product_id', 'id');
    }

    // Agar bisa dipakai RelationManager langsung
    public function shopifyVariants()
    {
        return $this->hasMany(\App\Models\ShopifyVariant::class, 'shopify_product_id', 'shopify_product_id');
    }

    public function shopifyImages()
    {
        return $this->hasMany(\App\Models\ShopifyImage::class, 'shopify_product_id', 'shopify_product_id');
    }

    public function getIsLowStockAttribute(): bool
    {
        return (int) $this->stock <= (int) config('inventory.low_stock_threshold', 5);
    }

}
