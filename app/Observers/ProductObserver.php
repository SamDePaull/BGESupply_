<?php

namespace App\Observers;

use App\Jobs\PushProductToShopify;
use App\Jobs\UpdateProductOnShopify;
use App\Jobs\DeleteProductOnShopify;
use App\Models\Product;

class ProductObserver
{
    /** Fields yang memicu update ke Shopify saat berubah */
    private array $watch = [
        'title',
        'description',
        'price',
        'sku',
        'inventory_quantity',
        'tags',
        'vendor'
    ];
    public function created(Product $product): void
    {
        if (empty($product->shopify_product_id)) {
            dispatch(new PushProductToShopify($product->id))->onQueue('shopify');
        }
    }
    public function updated(Product $product): void
    {
        $changes = array_keys($product->getChanges());
        $ignore = ['sync_status', 'last_synced_at', 'last_error', 'shopify_product_id', 'shopify_updated_at', 'updated_at', 'created_at', 'id'];
        $watchedChanged = array_diff(
            array_intersect($changes, $this->watch),
            $ignore
        );
        if (!empty($watchedChanged)) {
            $product->sync_status = 'dirty';
            $product->saveQuietly();
            if ($product->shopify_product_id) {
                dispatch(new UpdateProductOnShopify($product->id))->onQueue('shopify');
            } else {
                dispatch(new PushProductToShopify($product->id))->onQueue('shopify');
            }
        }
    }
    public function deleted(Product $product): void
    {
        if ($product->shopify_product_id) {
            dispatch(new DeleteProductOnShopify($product->id, $product->shopify_product_id))->onQueue('shopify');
        }
    }
}
