<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SyncLog;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class ShopifyPushService
{
    protected Client $http;
    protected string $base;
    protected string $version;
    protected string $shop;
    protected string $token;

    public function __construct()
    {
        $this->shop    = trim(config('services.shopify.shop', ''));
        $this->version = trim(config('services.shopify.version', '2025-07'));
        $this->token   = trim(config('services.shopify.token', ''));

        if ($this->shop === '' || $this->token === '') {
            throw new \RuntimeException('Set SHOPIFY_STORE_DOMAIN & SHOPIFY_ACCESS_TOKEN (lihat config/services.php).');
        }
        if (Str::contains($this->shop, '.')) {
            $this->shop = explode('.', $this->shop)[0];
        }

        $this->base = "https://{$this->shop}.myshopify.com/admin/api/{$this->version}/";
        $this->http = new Client([
            'base_uri'    => $this->base,
            'headers'     => [
                'X-Shopify-Access-Token' => $this->token,
                'Accept'                 => 'application/json',
                'Content-Type'           => 'application/json',
            ],
            'http_errors' => false,
            'timeout'     => 60,
        ]);
    }

    /** Normalisasi string untuk matching longgar */
    protected function norm(?string $v): string
    {
        return trim(mb_strtolower((string) $v));
    }
    /** Buat key gabungan opsi (o1|o2|o3) */
    protected function optKey(?string $o1, ?string $o2, ?string $o3): string
    {
        return implode('|', [$this->norm($o1), $this->norm($o2), $this->norm($o3)]);
    }

    /* =========================================================
     |  PUBLIC ENTRY POINTS
     * =======================================================*/

    /**
     * Wrapper kompatibilitas lama:
     * otomatis pilih create/update lalu pastikan inventory ter-push.
     * Biar pemanggilan lama `pushUnifiedToShopify($product)` tetap jalan.
     */
    public function pushUnifiedToShopify(Product $p, bool $forceRefreshInventory = true): void
    {
        if (empty($p->shopify_product_id)) {
            $this->createOnShopify($p);
            return;
        }

        $this->updateOnShopify($p);

        if ($forceRefreshInventory) {
            // pastikan id inventory terisi dan stok terkirim
            $this->refreshVariantInventoryIds($p);
            $this->reloadVariants($p);
            $this->pushInventoryLevels($p);
        }
    }

    public function createOnShopify(Product $p): void
    {
        [$options, $variants] = $this->buildOptionsAndVariantsPayload($p, false);
        $images  = $this->buildImagesForCreate($p);

        $payload = [
            'product' => array_filter([
                'title'        => $p->title,
                'handle'       => $p->handle,
                'body_html'    => $p->description,
                'vendor'       => $p->vendor,
                'product_type' => $p->product_type ?: ($p->category?->name),
                'tags'         => $p->tags,
                'options'      => $options ?: null,
                'variants'     => $variants,
                'images'       => !empty($images) ? $images : null,
                'status'       => $p->status ?: 'draft',
                'published_at' => $p->published_at?->toIso8601String(),
                'metafields_global_title_tag'       => $p->seo_title,
                'metafields_global_description_tag' => $p->seo_description,
            ], fn($v) => $v !== null && $v !== ''),
        ];

        $status = 0;
        $json   = $this->request('POST', 'products.json', ['json' => $payload], $status);
        $data   = json_decode($json, true) ?: [];
        $prod   = $data['product'] ?? null;

        if ($prod && isset($prod['id'])) {
            $p->shopify_product_id = (int) $prod['id'];
            $p->last_synced_at     = now();
            $p->sync_status        = 'ok';
            $p->saveQuietly();
        }

        $this->mapVariantIdsAfterCreate($p, $data);
        $this->reloadVariants($p);
        $this->pushInventoryLevels($p);

        $this->log('PushProductToShopify', 'create', $p->id, $status, 'ok', null, $data);
    }

    public function updateOnShopify(Product $p): void
    {
        [$options, $vars] = $this->buildOptionsAndVariantsPayload($p, true);

        $product = array_filter([
            'id'           => (int) $p->shopify_product_id,
            'title'        => $p->title,
            'handle'       => $p->handle,
            'body_html'    => $p->description,
            'vendor'       => $p->vendor,
            'product_type' => $p->product_type ?: ($p->category?->name),
            'tags'         => $p->tags,
            'options'      => $options ?: null,
            'variants'     => !empty($vars) ? $vars : null,
            'status'       => $p->status ?: 'draft',
            'published_at' => $p->published_at?->toIso8601String(),
            'metafields_global_title_tag'       => $p->seo_title,
            'metafields_global_description_tag' => $p->seo_description,
        ], fn($v) => $v !== null && $v !== '');

        if (array_key_exists('variants', $product) && empty($product['variants'])) {
            unset($product['variants']);
        }

        $status = 0;
        $json   = $this->request('PUT', "products/{$p->shopify_product_id}.json", ['json' => ['product' => $product]], $status);
        $data   = json_decode($json, true) ?: [];

        $p->last_synced_at = now();
        $p->sync_status    = ($status >= 200 && $status < 300) ? 'ok' : 'failed';
        $p->saveQuietly();

        // sinkron gambar & inventory_item_id terbaru
        $this->syncImagesOnUpdate($p);
        $this->refreshVariantInventoryIds($p);

        // reload varian sebelum push inventory
        $this->reloadVariants($p);
        $this->pushInventoryLevels($p);

        $this->log(
            'UpdateProductOnShopify',
            'update',
            $p->id,
            $status,
            ($status >= 200 && $status < 300) ? 'ok' : 'failed',
            null,
            $data
        );
    }

    public function deleteOnShopify(Product $p): void
    {
        // Jika belum pernah tersinkron ke Shopify, tidak perlu panggil API
        if (empty($p->shopify_product_id)) {
            $this->log(
                'DeleteProductOnShopify',
                'skip',
                $p->id,
                0,
                'skipped',
                'No shopify_product_id on product'
            );
            return;
        }

        $status = 0;
        $resp   = $this->request('DELETE', "products/{$p->shopify_product_id}.json", [], $status);

        $ok = ($status >= 200 && $status < 300);

        // Update metadata sinkronisasi (jika Anda tidak langsung menghapus offline)
        $p->last_synced_at = now();
        $p->sync_status    = $ok ? 'deleted' : 'failed';
        $p->saveQuietly();

        // Body biasanya kosong pada DELETE; aman jika null
        $body = json_decode($resp, true) ?: null;

        $this->log(
            'DeleteProductOnShopify',
            'delete',
            $p->id,
            $status,
            $ok ? 'ok' : 'failed',
            null,
            is_array($body) ? $body : null
        );
    }


    public function pushInventoryLevels(Product $p): void
    {
        $inv = app(ShopifyInventoryService::class);
        $loc = $inv->getDefaultLocationId();

        // kalau masih ada varian tanpa inventory_item_id, coba refresh sekali lagi
        if ($p->variants()->whereNull('shopify_inventory_item_id')->exists()) {
            $this->refreshVariantInventoryIds($p);
            $this->reloadVariants($p);
        }

        foreach ($p->variants as $v) {
            if (!$v->shopify_inventory_item_id) {
                $this->log('Inventory', 'skip', $p->id, 0, 'skipped', "No inventory_item_id for variant {$v->id}");
                continue;
            }

            // fallback qty dari produk kalau varian kosong
            $qty = is_numeric($v->inventory_quantity)
                ? (int) $v->inventory_quantity
                : (int) ($p->inventory_quantity ?? 0);

            try {
                $inv->setInventory((int) $v->shopify_inventory_item_id, $qty, $loc);

                // verifikasi (sangat membantu debugging)
                $after = $inv->getInventoryAvailable((int) $v->shopify_inventory_item_id, $loc);
                $this->log(
                    'Inventory',
                    'set',
                    $p->id,
                    200,
                    'ok',
                    "variant_id={$v->id}; inv_item={$v->shopify_inventory_item_id}; loc={$loc}; qty={$qty}; verified_available=" . var_export($after, true)
                );
            } catch (\Throwable $e) {
                $this->log('Inventory', 'set', $p->id, 0, 'failed', $e->getMessage());
            }
        }
    }

    /* =========================================================
     |  BUILDERS & HELPERS
     * =======================================================*/

    protected function buildOptionsAndVariantsPayload(Product $p, bool $forUpdate): array
    {
        $options = [];
        if ($p->option1_name) $options[] = ['name' => $p->option1_name, 'position' => 1];
        if ($p->option2_name) $options[] = ['name' => $p->option2_name, 'position' => 2];
        if ($p->option3_name) $options[] = ['name' => $p->option3_name, 'position' => 3];

        $variants = [];
        foreach ($p->variants as $v) {
            $row = array_filter([
                'id'                   => $forUpdate ? ($v->shopify_variant_id ? (int) $v->shopify_variant_id : null) : null,
                'sku'                  => $v->sku,
                'barcode'              => $v->barcode,
                'price'                => isset($v->price) ? (string) $v->price : null,
                'compare_at_price'     => isset($v->compare_at_price) ? (string) $v->compare_at_price : null,
                'option1'              => $v->option1_value,
                'option2'              => $v->option2_value,
                'option3'              => $v->option3_value,
                'requires_shipping'    => (bool) $v->requires_shipping,
                'taxable'              => (bool) $v->taxable,
                'weight'               => $v->weight ? (float) $v->weight : null,
                'weight_unit'          => $v->weight_unit,
                'inventory_management' => 'shopify',
            ], fn($x) => $x !== null && $x !== '');
            $variants[] = $row;
        }

        return [$options, $variants];
    }

    protected function buildImagesForCreate(Product $p): array
    {
        $out = [];
        foreach ($p->images as $img) {
            $entry = [
                'position' => $img->position,
                'alt'      => $img->alt,
            ];
            $path = storage_path('app/public/' . ltrim($img->file_path, '/'));
            if (is_file($path)) {
                $entry['attachment'] = base64_encode(file_get_contents($path));
            }
            $out[] = array_filter($entry, fn($v) => $v !== null && $v !== '');
        }
        return $out;
    }

    protected function request(string $method, string $uri, array $options = [], &$status = 0): string
    {
        $resp   = $this->http->request($method, $uri, $options);
        $status = $resp->getStatusCode();
        $body   = (string) $resp->getBody();
        return $body;
    }

    protected function log(string $job, string $action, int $productId, int $http, string $status, string $msg = null, array $body = null): void
    {
        SyncLog::create([
            'job'         => $job,
            'action'      => $action,
            'product_id'  => $productId,
            'http_status' => $http,
            'status'      => $status,
            'message'     => $msg,
            'body'        => $body,
        ]);
    }

    protected function mapVariantIdsAfterCreate(Product $p, array $resp): void
    {
        $remoteVariants = $resp['product']['variants'] ?? [];
        if (empty($remoteVariants)) return;

        $locals = $p->variants()->get();
        $bySku = $byOpts = $byTitle = [];

        foreach ($locals as $lv) {
            if ($lv->sku) $bySku[$this->norm($lv->sku)] = $lv;
            $byOpts[$this->optKey($lv->option1_value, $lv->option2_value, $lv->option3_value)] = $lv;
            if ($lv->title) $byTitle[$this->norm($lv->title)] = $lv;
        }

        foreach ($remoteVariants as $idx => $rv) {
            $sku = $this->norm($rv['sku'] ?? '');
            $key = $this->optKey($rv['option1'] ?? null, $rv['option2'] ?? null, $rv['option3'] ?? null);
            $rTitle = $this->norm($rv['title'] ?? '');

            $match = null;
            if ($sku !== '' && isset($bySku[$sku]))        $match = $bySku[$sku];
            elseif (isset($byOpts[$key]))                  $match = $byOpts[$key];
            elseif ($rTitle !== '' && isset($byTitle[$rTitle])) $match = $byTitle[$rTitle];
            else                                           $match = $locals[$idx] ?? null;

            if ($match) {
                $svId = (int)($rv['id'] ?? 0);
                $iiId = isset($rv['inventory_item_id']) ? (int)$rv['inventory_item_id'] : null;
                $changed = false;
                if ($svId && $match->shopify_variant_id !== $svId) {
                    $match->shopify_variant_id = $svId;
                    $changed = true;
                }
                if ($iiId && $match->shopify_inventory_item_id !== $iiId) {
                    $match->shopify_inventory_item_id = $iiId;
                    $changed = true;
                }
                if ($changed) $match->saveQuietly();
            }
        }
    }

    public function refreshVariantInventoryIds(Product $p): void
    {
        if (!$p->shopify_product_id) return;

        $json   = $this->request('GET', "products/{$p->shopify_product_id}.json");
        $remote = json_decode($json, true)['product']['variants'] ?? [];
        if (empty($remote)) return;

        $locals = $p->variants()->get();
        $bySku = $byOpts = $byTitle = [];

        foreach ($locals as $lv) {
            if ($lv->sku) $bySku[$this->norm($lv->sku)] = $lv;
            $byOpts[$this->optKey($lv->option1_value, $lv->option2_value, $lv->option3_value)] = $lv;
            if ($lv->title) $byTitle[$this->norm($lv->title)] = $lv;
        }

        foreach ($remote as $idx => $rv) {
            $sku = $this->norm($rv['sku'] ?? '');
            $key = $this->optKey($rv['option1'] ?? null, $rv['option2'] ?? null, $rv['option3'] ?? null);
            $rTitle = $this->norm($rv['title'] ?? '');

            $match = null;
            if ($sku !== '' && isset($bySku[$sku]))        $match = $bySku[$sku];
            elseif (isset($byOpts[$key]))                  $match = $byOpts[$key];
            elseif ($rTitle !== '' && isset($byTitle[$rTitle])) $match = $byTitle[$rTitle];
            else                                           $match = $locals[$idx] ?? null;

            if ($match) {
                $svId = (int)($rv['id'] ?? 0);
                $iiId = isset($rv['inventory_item_id']) ? (int)$rv['inventory_item_id'] : null;
                $changed = false;
                if ($svId && !$match->shopify_variant_id) {
                    $match->shopify_variant_id = $svId;
                    $changed = true;
                }
                if ($iiId && !$match->shopify_inventory_item_id) {
                    $match->shopify_inventory_item_id = $iiId;
                    $changed = true;
                }
                if ($changed) $match->saveQuietly();
            }
        }
    }

    protected function reloadVariants(Product $p): void
    {
        $p->unsetRelation('variants');
        $p->load('variants');
    }

    protected function syncImagesOnUpdate(Product $p): void
    {
        if (!$p->shopify_product_id) return;

        $json   = $this->request('GET', "products/{$p->shopify_product_id}/images.json");
        $remote = json_decode($json, true)['images'] ?? [];

        $remoteById = [];
        foreach ($remote as $ri) {
            $remoteById[(string) $ri['id']] = $ri;
        }

        foreach ($p->images as $img) {
            $payload = [
                'image' => array_filter([
                    'id'         => $img->shopify_image_id ? (int) $img->shopify_image_id : null,
                    'product_id' => (int) $p->shopify_product_id,
                    'position'   => $img->position,
                    'alt'        => $img->alt,
                ], fn($v) => $v !== null && $v !== ''),
            ];

            $path = storage_path('app/public/' . ltrim($img->file_path, '/'));
            if (!$img->shopify_image_id && is_file($path)) {
                $payload['image']['attachment'] = base64_encode(file_get_contents($path));
            }

            if ($img->shopify_image_id && isset($remoteById[(string) $img->shopify_image_id])) {
                $resp = $this->request('PUT', "products/{$p->shopify_product_id}/images/{$img->shopify_image_id}.json", ['json' => $payload]);
                $data = json_decode($resp, true);
                if (!empty($data['image']['src'])) {
                    $img->file_path = $data['image']['src']; // ← simpan URL Shopify
                    $img->saveQuietly();
                }
            } else {
                $resp = $this->request('POST', "products/{$p->shopify_product_id}/images.json", ['json' => $payload]);
                $data = json_decode($resp, true);
                if (!empty($data['image']['id'])) {
                    $img->shopify_image_id = (int) $data['image']['id'];
                    if (!empty($data['image']['src'])) {
                        $img->file_path = $data['image']['src']; // ← simpan URL Shopify
                    }
                    $img->saveQuietly();
                }
            }
        }
    }
}
