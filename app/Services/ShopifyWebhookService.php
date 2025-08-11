<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookService
{
    // Kurangi stok di unified products berdasarkan order line_items (SKU)
    public function handleOrderCreate(array $order): void
    {
        foreach ($order['line_items'] ?? [] as $line) {
            $sku = $line['sku'] ?? null;
            $qty = (int)($line['quantity'] ?? 0);
            if (!$sku || $qty <= 0) continue;

            $product = Product::where('sku', $sku)->first();
            if (!$product) continue;

            $new = max(0, (int)$product->stock - $qty);
            $product->update(['stock' => $new, 'sync_status' => 'synced']);
        }

        // (opsional) simpan order ke tabel orders
        \App\Models\Order::create([
            'source' => 'shopify',
            'order_number' => (string)($order['name'] ?? $order['id'] ?? now()->timestamp),
            'total_price' => (float)($order['total_price'] ?? 0),
            'raw_response' => $order,
        ]);

        Log::info('Shopify order processed', ['order_id' => $order['id'] ?? null]);
    }

    // Update produk saat ada perubahan di Shopify (judul, harga varian utama, stok, gambar)
    public function handleProductUpdate(array $productPayload): void
    {
        $shopifyId = $productPayload['id'] ?? null;
        if (!$shopifyId) return;

        // gunakan service yang sama untuk konsistensi
        app(\App\Services\ShopifyService::class)->pullSingleProductAndIngest((int)$shopifyId);

        Log::info('Shopify product updated via webhook', ['shopify_id' => $shopifyId]);
    }
}
