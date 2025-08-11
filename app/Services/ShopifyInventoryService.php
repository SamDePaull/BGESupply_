<?php

namespace App\Services;

use App\Models\ShopifyVariant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyInventoryService
{
    // Simple approach: cari variant by SKU (di DB kita), ambil inventory_item_id, panggil inventory adjustments
    public function syncVariantInventoryBySku(?string $sku, int $newQty): bool
    {
        if (!$sku) return false;

        $variant = ShopifyVariant::where('sku', $sku)->first();
        if (!$variant || !$variant->inventory_item_id) return false;

        // Shopify Inventory Levels API: set or adjust – per lokasi (butuh location_id)
        // Di sini contoh adjust sederhana (kamu perlu ambil location_id default toko)
        $locationId = config('services.shopify.location_id');
        if (!$locationId) {
            Log::warning('Shopify location_id not set');
            return false;
        }

        // Set inventory level
        $resp = Http::withHeaders([
            'X-Shopify-Access-Token' => config('services.shopify.token'),
        ])->post("https://" . config('services.shopify.domain') . "/admin/api/2025-07/inventory_levels/set.json", [
            'location_id' => (int)$locationId,
            'inventory_item_id' => (int)$variant->inventory_item_id,
            'available' => (int)$newQty,
        ]);

        if (!$resp->successful()) {
            Log::error('Inventory set failed', ['body' => $resp->body()]);
            return false;
        }

        // update cache stok di variant
        $variant->update(['inventory_quantity' => $newQty]);

        return true;
    }
}
