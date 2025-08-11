<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyPushService
{
    public function pushUnifiedToShopify(Product $product): bool
    {
        try {
            $resp = Http::withHeaders([
                'X-Shopify-Access-Token' => config('services.shopify.token'),
            ])->post("https://" . config('services.shopify.domain') . "/admin/api/2025-07/products.json", [
                'product' => [
                    'title'   => $product->name,
                    'variants'=> [[
                        'price'                 => $product->price,
                        'inventory_management'  => 'shopify',
                        'inventory_quantity'    => $product->stock,
                        'sku'                   => $product->sku,
                    ]],
                    'images'  => $product->image_url ? [['src' => $product->image_url]] : [],
                ],
            ]);

            if (!$resp->successful()) {
                Log::error('Push Shopify failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                $product->update(['sync_status' => 'failed']);
                return false;
            }

            $payload = $resp->json('product');

            $product->update([
                // 'origin'             => 'offline',
                // 'origin_id'          => $payload['id'] ?? null,
                'shopify_product_id' => $payload['id'] ?? null,
                // 'is_from_shopify'    => false,
                'sync_status'        => 'synced',
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Push Shopify exception', ['e' => $e->getMessage()]);
            $product->update(['sync_status' => 'failed']);
            return false;
        }
    }

    public function deleteOnShopify(int $shopifyProductId): bool
    {
        try {
            $resp = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Shopify-Access-Token' => config('services.shopify.token'),
            ])->delete("https://" . config('services.shopify.domain') . "/admin/api/2025-07/products/{$shopifyProductId}.json");

            if (!$resp->successful() && $resp->status() !== 404) {
                \Log::error('Delete Shopify failed', ['id' => $shopifyProductId, 'status' => $resp->status(), 'body' => $resp->body()]);
                return false;
            }

            // bersihkan data lokal terkait produk ini (opsional sesuai kebijakan)
            \App\Models\ShopifyProduct::where('id', $shopifyProductId)->delete();
            \App\Models\ShopifyVariant::where('shopify_product_id', $shopifyProductId)->delete();
            \App\Models\ShopifyImage::where('shopify_product_id', $shopifyProductId)->delete();
            \App\Models\ShopifyProductRaw::where('shopify_product_id', $shopifyProductId)->delete();

            // untuk unified product: JANGAN ubah origin, cukup lepaskan tautan ke Shopify
            \App\Models\Product::where('shopify_product_id', $shopifyProductId)->update([
                'shopify_product_id' => null,
                'sync_status'        => 'synced',
                // 'is_from_shopify' tetap sesuai sumber awal
            ]);

            return true;
        } catch (\Throwable $e) {
            \Log::error('Delete Shopify exception', ['e' => $e->getMessage()]);
            return false;
        }
    }
}
