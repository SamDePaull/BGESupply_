<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'title',
        'description',
        'sku',
        'price',
        'inventory_quantity',
        'tags',
        'vendor',
        'options_schema',
        'option1_name',
        'option2_name',
        'option3_name',
        'shopify_product_id',
        'sync_status',
        'last_synced_at',
        'last_error',
        'shopify_updated_at',
    ];

    protected $casts = [
        'options_schema' => 'array',
        'last_synced_at' => 'datetime',
        'shopify_updated_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    // Default nilai agar kalau field tidak dikirim, tetap 0
    protected $attributes = [
        'inventory_quantity' => 0,
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
    public function markDirty(string $reason = null): void
    {
        $this->sync_status = 'dirty';
        if ($reason) {
            $this->last_error = $reason; // keep latest reason for visibility
        }
        $this->saveQuietly();
    }


    /** Scope: dirty rows only. */
    public function scopeDirty($q)
    {
        return $q->where('sync_status', 'dirty');
    }

    public function syncLogs()
    {
        return $this->hasMany(\App\Models\SyncLog::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function setInventoryQuantityAttribute($value): void
    {
        $this->attributes['inventory_quantity'] =
            ($value === null || $value === '') ? 0 : (int) $value;
    }
}
