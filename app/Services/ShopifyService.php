<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShopifyProductRaw;
use App\Models\ShopifyProduct;
use App\Models\ShopifyVariant;
use App\Models\ShopifyImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class ShopifyService
{
    public function pullAndIngest(): int
    {
        $resp = Http::withHeaders([
            'X-Shopify-Access-Token' => config('services.shopify.token'),
        ])->get("https://" . config('services.shopify.domain') . "/admin/api/2025-07/products.json", [
            'fields' => 'id,title,vendor,handle,body_html,status,product_type,template_suffix,tags,published_at,admin_graphql_api_id,variants,image,images,options',
            'limit'  => 250,
        ]);

        if (!$resp->successful()) {
            Log::error('Shopify pull failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            return 0;
        }

        $items = $resp->json('products') ?? [];
        $processed = 0;

        foreach ($items as $p) {
            // 1) RAW
            ShopifyProductRaw::updateOrCreate(
                ['shopify_product_id' => $p['id']],
                ['payload' => $p, 'fetched_at' => now()]
            );

            // 2) FLATTEN product
            ShopifyProduct::updateOrCreate(
                ['id' => $p['id']],
                [
                    'title'                 => $p['title'] ?? null,
                    'vendor'                => $p['vendor'] ?? null,
                    'handle'                => $p['handle'] ?? null,
                    'body_html'             => $p['body_html'] ?? null,
                    'status'                => $p['status'] ?? null,
                    'product_type'          => $p['product_type'] ?? null,
                    'template_suffix'       => $p['template_suffix'] ?? null,
                    'tags'                  => is_array($p['tags'] ?? null) ? implode(',', $p['tags']) : ($p['tags'] ?? null),
                    'published_at'          => $p['published_at'] ?? null,
                    'admin_graphql_api_id'  => $p['admin_graphql_api_id'] ?? null,
                    'options'               => $p['options'] ?? null,
                    'metafields'            => null,
                    'image_url'             => Arr::get($p, 'image.src'),
                    'extra'                 => null,
                ]
            );

            // 3) FLATTEN variants
            foreach ($p['variants'] ?? [] as $v) {
                ShopifyVariant::updateOrCreate(
                    ['id' => $v['id']],
                    [
                        'shopify_product_id'     => $p['id'],
                        'title'                  => $v['title'] ?? null,
                        'price'                  => $v['price'] ?? null,
                        'position'               => $v['position'] ?? null,
                        'inventory_policy'       => $v['inventory_policy'] ?? null,
                        'compare_at_price'       => $v['compare_at_price'] ?? null,
                        'option1'                => $v['option1'] ?? null,
                        'option2'                => $v['option2'] ?? null,
                        'option3'                => $v['option3'] ?? null,
                        'shopify_created_at'     => $v['created_at'] ?? null,
                        'shopify_updated_at'     => $v['updated_at'] ?? null,
                        'taxable'                => (bool)($v['taxable'] ?? true),
                        'barcode'                => $v['barcode'] ?? null,
                        'fulfillment_service'    => $v['fulfillment_service'] ?? null,
                        'grams'                  => $v['grams'] ?? null,
                        'inventory_management'   => $v['inventory_management'] ?? null,
                        'requires_shipping'      => (bool)($v['requires_shipping'] ?? true),
                        'sku'                    => $v['sku'] ?? null,
                        'weight'                 => $v['weight'] ?? null,
                        'weight_unit'            => $v['weight_unit'] ?? null,
                        'inventory_item_id'      => $v['inventory_item_id'] ?? null,
                        'inventory_quantity'     => $v['inventory_quantity'] ?? 0,
                        'old_inventory_quantity' => $v['old_inventory_quantity'] ?? null,
                        'admin_graphql_api_id'   => $v['admin_graphql_api_id'] ?? null,
                        'image_id'               => $v['image_id'] ?? null,
                        'extra'                  => null,
                    ]
                );
            }

            // 4) FLATTEN images
            if (!empty($p['images']) && is_array($p['images'])) {
                foreach ($p['images'] as $img) {
                    $imgId = $img['id'] ?? null;
                    if (!$imgId) continue;

                    ShopifyImage::updateOrCreate(
                        ['id' => $imgId],
                        [
                            'shopify_product_id'   => $p['id'],
                            'position'             => $img['position'] ?? null,
                            'src'                  => $img['src'] ?? null,
                            'width'                => $img['width'] ?? null,
                            'height'               => $img['height'] ?? null,
                            'alt'                  => $img['alt'] ?? null,
                            'admin_graphql_api_id' => $img['admin_graphql_api_id'] ?? null,
                            'shopify_created_at'   => $img['created_at'] ?? null,
                            'shopify_updated_at'   => $img['updated_at'] ?? null,
                            'variant_ids'          => $img['variant_ids'] ?? null,
                            'extra'                => null,
                        ]
                    );
                }
            } elseif (!empty($p['image'])) {
                $img = $p['image'];
                $imgId = $img['id'] ?? null;

                if ($imgId) {
                    ShopifyImage::updateOrCreate(
                        ['id' => $imgId],
                        [
                            'shopify_product_id'   => $p['id'],
                            'position'             => $img['position'] ?? 1,
                            'src'                  => $img['src'] ?? null,
                            'width'                => $img['width'] ?? null,
                            'height'               => $img['height'] ?? null,
                            'alt'                  => $img['alt'] ?? null,
                            'admin_graphql_api_id' => $img['admin_graphql_api_id'] ?? null,
                            'shopify_created_at'   => $img['created_at'] ?? null,
                            'shopify_updated_at'   => $img['updated_at'] ?? null,
                            'variant_ids'          => $img['variant_ids'] ?? [],
                            'extra'                => null,
                        ]
                    );
                }
            }

            // 5) MERGE KE UNIFIED `products`
            $first = $p['variants'][0] ?? null;
            if ($first) {
                // cari unified yang SUDAH terhubung ke Shopify (bisa asalnya offline)
                $existingUnified = Product::where('shopify_product_id', $p['id'])->first();

                $updatable = [
                    'name'      => $p['title'] ?? null,
                    'price'     => isset($first['price']) ? (float)$first['price'] : null,
                    'stock'     => isset($first['inventory_quantity']) ? (int)$first['inventory_quantity'] : null,
                    'image_url' => Arr::get($p, 'image.src'),
                    'sync_status' => 'synced',
                ];

                // jangan timpa sku jika sudah ada; kalau kosong, isi dari variant
                if (empty($existingUnified?->sku)) {
                    $updatable['sku'] = $first['sku'] ?? ('SHOPIFY-' . $first['id']);
                }

                if ($existingUnified) {
                    // UPDATE tanpa mengubah origin/is_from_shopify
                    $existingUnified->fill(array_filter($updatable, fn($v) => !is_null($v)))->save();
                } else {
                    // belum ada unified yang link ke Shopify → buat baru sebagai produk asal Shopify
                    Product::updateOrCreate(
                        ['origin' => 'shopify', 'origin_id' => $p['id']],
                        [
                            'name'               => $updatable['name'] ?? 'No Title',
                            'sku'                => $first['sku'] ?? ('SHOPIFY-' . $first['id']),
                            'price'              => $updatable['price'] ?? 0,
                            'cost_price'         => null,
                            'stock'              => $updatable['stock'] ?? 0,
                            'image_url'          => $updatable['image_url'] ?? null,
                            'shopify_product_id' => $p['id'],
                            'is_from_shopify'    => true,
                            'sync_status'        => 'synced',
                        ]
                    );
                }

                $processed++;
            }
        }

        Log::info('Shopify pull & ingest done', ['count' => $processed]);
        return $processed;
    }

    public function pullSingleProductAndIngest(int $shopifyProductId): bool
    {
        $resp = Http::withHeaders([
            'X-Shopify-Access-Token' => config('services.shopify.token'),
        ])->get("https://" . config('services.shopify.domain') . "/admin/api/2025-07/products/{$shopifyProductId}.json", [
            'fields' => 'id,title,vendor,handle,body_html,status,product_type,template_suffix,tags,published_at,admin_graphql_api_id,variants,image,images,options',
        ]);

        if (!$resp->successful()) {
            Log::error('Pull single product failed', ['id' => $shopifyProductId, 'body' => $resp->body()]);
            return false;
        }

        $p = $resp->json('product');
        if (!$p) return false;

        // RAW
        ShopifyProductRaw::updateOrCreate(
            ['shopify_product_id' => $p['id']],
            ['payload' => $p, 'fetched_at' => now()]
        );

        // FLATTEN product
        ShopifyProduct::updateOrCreate(
            ['id' => $p['id']],
            [
                'title'                 => $p['title'] ?? null,
                'vendor'                => $p['vendor'] ?? null,
                'handle'                => $p['handle'] ?? null,
                'body_html'             => $p['body_html'] ?? null,
                'status'                => $p['status'] ?? null,
                'product_type'          => $p['product_type'] ?? null,
                'template_suffix'       => $p['template_suffix'] ?? null,
                'tags'                  => is_array($p['tags'] ?? null) ? implode(',', $p['tags']) : ($p['tags'] ?? null),
                'published_at'          => $p['published_at'] ?? null,
                'admin_graphql_api_id'  => $p['admin_graphql_api_id'] ?? null,
                'options'               => $p['options'] ?? null,
                'metafields'            => null,
                'image_url'             => Arr::get($p, 'image.src'),
                'extra'                 => null,
            ]
        );

        // FLATTEN variants
        foreach ($p['variants'] ?? [] as $v) {
            ShopifyVariant::updateOrCreate(
                ['id' => $v['id']],
                [
                    'shopify_product_id'     => $p['id'],
                    'title'                  => $v['title'] ?? null,
                    'price'                  => $v['price'] ?? null,
                    'position'               => $v['position'] ?? null,
                    'inventory_policy'       => $v['inventory_policy'] ?? null,
                    'compare_at_price'       => $v['compare_at_price'] ?? null,
                    'option1'                => $v['option1'] ?? null,
                    'option2'                => $v['option2'] ?? null,
                    'option3'                => $v['option3'] ?? null,
                    'shopify_created_at'     => $v['created_at'] ?? null,
                    'shopify_updated_at'     => $v['updated_at'] ?? null,
                    'taxable'                => (bool)($v['taxable'] ?? true),
                    'barcode'                => $v['barcode'] ?? null,
                    'fulfillment_service'    => $v['fulfillment_service'] ?? null,
                    'grams'                  => $v['grams'] ?? null,
                    'inventory_management'   => $v['inventory_management'] ?? null,
                    'requires_shipping'      => (bool)($v['requires_shipping'] ?? true),
                    'sku'                    => $v['sku'] ?? null,
                    'weight'                 => $v['weight'] ?? null,
                    'weight_unit'            => $v['weight_unit'] ?? null,
                    'inventory_item_id'      => $v['inventory_item_id'] ?? null,
                    'inventory_quantity'     => $v['inventory_quantity'] ?? 0,
                    'old_inventory_quantity' => $v['old_inventory_quantity'] ?? null,
                    'admin_graphql_api_id'   => $v['admin_graphql_api_id'] ?? null,
                    'image_id'               => $v['image_id'] ?? null,
                    'extra'                  => null,
                ]
            );
        }

        // IMAGES
        if (!empty($p['images']) && is_array($p['images'])) {
            foreach ($p['images'] as $img) {
                if (!isset($img['id'])) continue;
                ShopifyImage::updateOrCreate(
                    ['id' => $img['id']],
                    [
                        'shopify_product_id'   => $p['id'],
                        'position'             => $img['position'] ?? null,
                        'src'                  => $img['src'] ?? null,
                        'width'                => $img['width'] ?? null,
                        'height'               => $img['height'] ?? null,
                        'alt'                  => $img['alt'] ?? null,
                        'admin_graphql_api_id' => $img['admin_graphql_api_id'] ?? null,
                        'shopify_created_at'   => $img['created_at'] ?? null,
                        'shopify_updated_at'   => $img['updated_at'] ?? null,
                        'variant_ids'          => $img['variant_ids'] ?? [],
                        'extra'                => null,
                    ]
                );
            }
        }

        // 5) MERGE KE UNIFIED
        $first = $p['variants'][0] ?? null;
        if ($first) {
            $existingUnified = Product::where('shopify_product_id', $p['id'])->first();

            $updatable = [
                'name'      => $p['title'] ?? null,
                'price'     => isset($first['price']) ? (float)$first['price'] : null,
                'stock'     => isset($first['inventory_quantity']) ? (int)$first['inventory_quantity'] : null,
                'image_url' => Arr::get($p, 'image.src'),
                'sync_status' => 'synced',
            ];

            if (empty($existingUnified?->sku)) {
                $updatable['sku'] = $first['sku'] ?? ('SHOPIFY-' . $first['id']);
            }

            if ($existingUnified) {
                $existingUnified->fill(array_filter($updatable, fn($v) => !is_null($v)))->save();
            } else {
                Product::updateOrCreate(
                    ['origin' => 'shopify', 'origin_id' => $p['id']],
                    [
                        'name'               => $updatable['name'] ?? 'No Title',
                        'sku'                => $first['sku'] ?? ('SHOPIFY-' . $first['id']),
                        'price'              => $updatable['price'] ?? 0,
                        'cost_price'         => null,
                        'stock'              => $updatable['stock'] ?? 0,
                        'image_url'          => $updatable['image_url'] ?? null,
                        'shopify_product_id' => $p['id'],
                        'is_from_shopify'    => true,
                        'sync_status'        => 'synced',
                    ]
                );
            }
        }

        return true;
    }
}
