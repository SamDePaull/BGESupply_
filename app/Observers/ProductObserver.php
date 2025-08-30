<?php

namespace App\Observers;

use App\Jobs\PushProductToShopify;
use App\Jobs\UpdateProductOnShopify;
use App\Jobs\DeleteProductOnShopify;
use App\Models\OfflineProductStaging;
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
    public function created(Product $p): void
    {
        if (empty($p->shopify_product_id)) {
            dispatch(new PushProductToShopify($p->id))->onQueue('shopify');
        }
        OfflineProductStaging::updateOrCreate(
            ['handle' => $p->handle],
            [
                'title'              => $p->title,
                'sku'                => $p->sku,
                'barcode'            => $p->barcode,
                'price'              => $p->price,
                'compare_at_price'   => $p->compare_at_price,
                'inventory_quantity' => $p->inventory_quantity,
                'vendor'             => $p->vendor,
                'product_type'       => $p->product_type,
                'options'            => array_values(array_filter([
                    $p->option1_name ? ['name' => $p->option1_name, 'values' => []] : null,
                    $p->option2_name ? ['name' => $p->option2_name, 'values' => []] : null,
                    $p->option3_name ? ['name' => $p->option3_name, 'values' => []] : null,
                ])),
                'variants'           => [],
                'images'             => [],
                'status'             => 'created_from_admin',
                'notes'              => null,
            ]
        );
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
