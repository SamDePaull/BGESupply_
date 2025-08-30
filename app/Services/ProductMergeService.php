<?php

namespace App\Services;

use App\Models\OfflineProductStaging;
use App\Models\ShopifyProductStaging;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductMergeService
{
    /** Merge satu row OFFLINE staging ke products (+variants +images) */
    public function mergeOffline(OfflineProductStaging $s): Product
    {
        return DB::transaction(function () use ($s) {
            $p = Product::updateOrCreate(
                ['handle' => $s->handle ?: Str::slug($s->title)],
                [
                    'title' => $s->title,
                    'description' => null,
                    'vendor' => $s->vendor,
                    'product_type' => $s->product_type,
                    'tags' => null,
                    'origin' => 'offline',
                    'sync_channel' => 'offline',
                    'merged_from' => ['offline_id' => $s->id],
                    // fallback stok & price kalau tidak ada varian
                    'inventory_quantity' => $s->inventory_quantity ?? 0,
                    'price' => $s->price,
                    'compare_at_price' => $s->compare_at_price,
                ]
            );

            // options
            if (!empty($s->options)) {
                $p->option1_name = Arr::get($s->options, '0.name');
                $p->option2_name = Arr::get($s->options, '1.name');
                $p->option3_name = Arr::get($s->options, '2.name');
                $p->saveQuietly();
            }

            // variants
            $incoming = $s->variants ?: [];
            if (empty($incoming)) {
                // single "default" variant dari baris produk offline
                $incoming = [[
                    'title' => 'Default',
                    'sku' => $s->sku,
                    'barcode' => $s->barcode,
                    'price' => $s->price,
                    'compare_at_price' => $s->compare_at_price,
                    'option1' => Arr::get($s->options, '0.values.0'),
                    'option2' => Arr::get($s->options, '1.values.0'),
                    'option3' => Arr::get($s->options, '2.values.0'),
                    'inventory_quantity' => $s->inventory_quantity ?? 0,
                    'requires_shipping' => true,
                    'taxable' => true,
                    'weight' => null,
                    'weight_unit' => 'kg',
                ]];
            }

            // sinkron varian by (sku|options|title)
            $existing = $p->variants()->get();
            foreach ($incoming as $idx => $v) {
                $sku = trim((string)($v['sku'] ?? ''));
                $o1  = $v['option1'] ?? null;
                $o2  = $v['option2'] ?? null;
                $o3  = $v['option3'] ?? null;
                $title = $v['title'] ?? null;

                $match = null;
                if ($sku !== '') $match = $existing->firstWhere('sku', $sku);
                if (!$match) {
                    $match = $existing->first(function ($ev) use ($o1, $o2, $o3) {
                        return $ev->option1_value === $o1 && $ev->option2_value === $o2 && $ev->option3_value === $o3;
                    });
                }
                if (!$match && $title) $match = $existing->firstWhere('title', $title);

                $payload = [
                    'title' => $title,
                    'sku' => $sku ?: null,
                    'barcode' => $v['barcode'] ?? null,
                    'price' => $v['price'] ?? null,
                    'compare_at_price' => $v['compare_at_price'] ?? null,
                    'option1_value' => $o1,
                    'option2_value' => $o2,
                    'option3_value' => $o3,
                    'inventory_quantity' => (int)($v['inventory_quantity'] ?? 0),
                    'requires_shipping' => (bool)($v['requires_shipping'] ?? true),
                    'taxable' => (bool)($v['taxable'] ?? true),
                    'weight' => $v['weight'] ?? null,
                    'weight_unit' => $v['weight_unit'] ?? 'kg',
                    'origin' => 'offline',
                    'sync_enabled' => false, // default: offline tidak auto-push ke Shopify
                ];

                if ($match) {
                    $match->fill($payload)->saveQuietly();
                } else {
                    $p->variants()->create($payload);
                }
            }

            // images
            foreach (($s->images ?? []) as $i => $img) {
                $path = ltrim((string)($img['path'] ?? ''), '/');
                if ($path === '') continue;
                $p->images()->firstOrCreate(
                    ['file_path' => $path],
                    ['position' => $i + 1, 'alt' => $img['alt'] ?? $p->title]
                );
            }

            $p->last_synced_at = now();
            $p->saveQuietly();

            $s->status = 'merged';
            $s->saveQuietly();

            return $p->fresh(['variants', 'images']);
        });
    }

    /** Merge satu row SHOPIFY staging ke products (+variants +images) */
    public function mergeShopify(ShopifyProductStaging $s): Product
    {
        $payload = $s->payload;
        $prod = $payload['product'] ?? $payload; // antisipasi bentuk
        return DB::transaction(function () use ($s, $prod) {
            $p = Product::updateOrCreate(
                ['shopify_product_id' => (int)$prod['id']],
                [
                    'title' => $prod['title'] ?? 'Untitled',
                    'handle' => $prod['handle'] ?? null,
                    'description' => $prod['body_html'] ?? null,
                    'vendor' => $prod['vendor'] ?? null,
                    'product_type' => $prod['product_type'] ?? null,
                    'tags' => $prod['tags'] ?? null,
                    'origin' => 'shopify',
                    'sync_channel' => 'shopify',
                    'merged_from' => ['shopify_staging_id' => $s->id],
                    'status' => $prod['status'] ?? 'draft',
                ]
            );

            // options—Shopify format: [{name,position,values:[]}]
            $opts = $prod['options'] ?? [];
            $p->option1_name = $opts[0]['name'] ?? $p->option1_name;
            $p->option2_name = $opts[1]['name'] ?? $p->option2_name;
            $p->option3_name = $opts[2]['name'] ?? $p->option3_name;
            $p->saveQuietly();

            // variants
            $remoteVars = $prod['variants'] ?? [];
            foreach ($remoteVars as $rv) {
                $payload = [
                    'title' => $rv['title'] ?? null,
                    'sku' => $rv['sku'] ?? null,
                    'barcode' => $rv['barcode'] ?? null,
                    'price' => isset($rv['price']) ? (string)$rv['price'] : null,
                    'compare_at_price' => isset($rv['compare_at_price']) ? (string)$rv['compare_at_price'] : null,
                    'option1_value' => $rv['option1'] ?? null,
                    'option2_value' => $rv['option2'] ?? null,
                    'option3_value' => $rv['option3'] ?? null,
                    'requires_shipping' => (bool)($rv['requires_shipping'] ?? true),
                    'taxable' => (bool)($rv['taxable'] ?? true),
                    'weight' => $rv['weight'] ?? null,
                    'weight_unit' => $rv['weight_unit'] ?? 'kg',
                    'shopify_variant_id' => (int)($rv['id'] ?? 0),
                    'shopify_inventory_item_id' => isset($rv['inventory_item_id']) ? (int)$rv['inventory_item_id'] : null,
                    'origin' => 'shopify',
                    'sync_enabled' => true,
                ];

                // match by shopify_variant_id or sku or options
                $match = $p->variants()->where('shopify_variant_id', (int)($rv['id'] ?? 0))->first();
                if (!$match && !empty($payload['sku'])) {
                    $match = $p->variants()->where('sku', $payload['sku'])->first();
                }
                if (!$match) {
                    $match = $p->variants()->where(function ($q) use ($payload) {
                        $q->where('option1_value', $payload['option1_value'])
                            ->where('option2_value', $payload['option2_value'])
                            ->where('option3_value', $payload['option3_value']);
                    })->first();
                }

                if ($match) $match->fill($payload)->saveQuietly();
                else $p->variants()->create($payload);
            }

            // images
            foreach (($prod['images'] ?? []) as $i => $img) {
                $src = $img['src'] ?? null;
                if (!$src) continue;
                $p->images()->firstOrCreate(
                    ['shopify_image_id' => (int)($img['id'] ?? 0)],
                    ['position' => $i + 1, 'alt' => $img['alt'] ?? $p->title, 'file_path' => $src]
                );
            }

            $p->last_synced_at = now();
            $p->saveQuietly();

            $s->status = 'merged';
            $s->saveQuietly();

            return $p->fresh(['variants', 'images']);
        });
    }
}
