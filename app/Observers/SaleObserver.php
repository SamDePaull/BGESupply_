<?php

namespace App\Observers;

use App\Models\Sale;
use App\Services\ShopifyInventoryService;

class SaleObserver
{
    public function created(Sale $sale): void
    {
        foreach ($sale->items as $item) {
            $product = $item->product;
            if (!$product) continue;

            // kurangi stok unified
            $product->decrement('stock', $item->quantity);

            // opsional: push perubahan stok ke Shopify kalau produk ini asalnya Shopify
            if ($product->shopify_product_id) {
                app(ShopifyInventoryService::class)->syncVariantInventoryBySku($product->sku, $product->stock);
            }
        }
    }
}
