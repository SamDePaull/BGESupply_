<?php

namespace App\Services;

use App\Exceptions\ShopifyHttpException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopifyPushService
{
    protected Client $http;
    protected string $base;
    protected string $version;
    protected string $shop;
    public function __construct()
    {
        $this->shop = trim(config('services.shopify.shop', ''));
        $this->version = trim(config('services.shopify.version', '2024-07'));
        $token = trim(config('services.shopify.token', ''));
        if ($this->shop === '' || $token === '') {
            throw new \RuntimeException('Shopify config missing: set
SHOPIFY_SHOP & SHOPIFY_TOKEN in .env');
        }
        if (Str::contains($this->shop, '.')) {
            $this->shop = explode('.', $this->shop)[0];
        }
        $this->base = "https://{$this->shop}.myshopify.com/admin/api/{$this->version}/";
        $this->http = new Client([
            'base_uri' => $this->base,
            'headers' => [
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }
    /** CREATE */
    public function pushUnifiedToShopify(Product $p): void
    {
        [$options, $variants] = $this->buildOptionsAndVariantsPayload(
            $p,
            false
        );
        if (empty($variants)) {
            // Single-variant fallback
            $variants = [[
                'sku' => $p->sku,
                'price' => (string) $p->price,
                'inventory_policy' => 'deny',
            ]];
        }
        $payload = [
            'product' => array_filter([
                'title' => $p->title,
                'body_html' => $p->description,
                'vendor' => $p->vendor,
                'tags' => $p->tags,
                'options' => $options ?: null,
                'variants' => $variants,
                'status' => 'active',
            ], fn($v) => $v !== null && $v !== '')
        ];
        $json = $this->request(
            'POST',
            'products.json',
            ['json' => $payload],
            $status
        );
        $data = json_decode($json, true);
        $shopifyProduct = $data['product'] ?? null;
        if (!$shopifyProduct) throw new \RuntimeException('Invalid Shopify
response on create');
        $p->shopify_product_id = $shopifyProduct['id'];
        $p->last_synced_at = now();
        $p->sync_status = 'synced';
        $p->last_error = null;
        $p->saveQuietly();
        $this->syncLocalVariantsFromResponse($p, $shopifyProduct);
        $this->pushInventoryLevels($p); // set stok per varian
        $this->log('PushProductToShopify', 'create', $p, $status['code'] ??
            null, 'ok', 'created', [
            'resp' => Arr::only($shopifyProduct, ['id', 'title', 'status'])
        ]);
    }
    /** UPDATE */
    public function updateUnifiedToShopify(Product $p): void
    {
        if (!$p->shopify_product_id) {
            throw new \RuntimeException("No shopify_product_id on product #{$p->id} — push first.");
        }
        [$options, $variantsPayload] = $this->buildOptionsAndVariantsPayload(
            $p,
            true
        );
        $product = array_filter([
            'id' => (int) $p->shopify_product_id,
            'title' => $p->title,
            'body_html' => $p->description,
            'vendor' => $p->vendor,
            'tags' => $p->tags,
            'options' => $options ?: null,
            'variants' => !empty($variantsPayload) ? $variantsPayload : null,
        ], fn($v) => $v !== null && $v !== '');
        if (
            array_key_exists('variants', $product) &&
            empty($product['variants'])
        ) unset($product['variants']);
        $json = $this->request(
            'PUT',
            "products/{$p->shopify_product_id}.json",
            ['json' => ['product' => $product]],
            $status
        );
        $data = json_decode($json, true);
        $shopifyProduct = $data['product'] ?? null;
        if ($shopifyProduct) $this->syncLocalVariantsFromResponse(
            $p,
            $shopifyProduct
        );
        $this->pushInventoryLevels($p); // pastikan stok terset sesuai lokal
        $p->shopify_updated_at = now();
        $p->last_error = null;
        $p->sync_status = 'synced';
        $p->last_synced_at = now();
        $p->saveQuietly();
        $this->log('UpdateProductOnShopify', 'update', $p, $status['code'] ??
            null, 'ok', 'updated', [
            'sent_variants' => !empty($variantsPayload),
        ]);
    }
    public function deleteOnShopify(int $shopifyProductId, ?Product $p = null): void
    {
        $this->request(
            'DELETE',
            "products/{$shopifyProductId}.json",
            [],
            $status
        );
        if ($p) $this->log(
            'DeleteProductOnShopify',
            'delete',
            $p,
            $status['code'] ?? null,
            'ok',
            'deleted'
        );
    }
    /** Build options + variants payload dari data lokal (maks 3 opsi) */
    protected function buildOptionsAndVariantsPayload(Product $p, bool
    $forUpdate): array
    {
        // options dari options_schema atau dari kolom optionX_name
        $options = [];
        $schema = $p->options_schema ?: [];
        if (!empty($schema)) {
            foreach (array_slice($schema, 0, 3) as $it) {
                $name = trim((string)($it['name'] ?? ''));
                if ($name === '') continue;
                $options[] = ['name' => $name];
            }
        } else {
            foreach (
                [$p->option1_name, $p->option2_name, $p->option3_name] as
                $nm
            ) {
                if ($nm) $options[] = ['name' => $nm];
            }
        }
        $variantsPayload = [];
        $variants = $p->variants()->orderBy('id')->get();
        foreach ($variants as $v) {
            $row = array_filter([
                'id' => $forUpdate ? ($v->shopify_variant_id ? (int) $v->shopify_variant_id : null) : null,
                'sku' => $v->sku,
                'price' => isset($v->price) ? (string) $v->price : null,
                'option1' => $v->option1_value,
                'option2' => $v->option2_value,
                'option3' => $v->option3_value,
                'inventory_management' => 'shopify',
            ], fn($x) => $x !== null && $x !== '');
            if (empty($options) && !isset($row['title'])) {
                $row['title'] = $v->title ?: 'Default';
            }
            $variantsPayload[] = $row;
        }
        if ($forUpdate && empty($variantsPayload)) return [$options, []];
        return [$options, $variantsPayload];
    }
    /** Update mapping variant lokal dari respons Shopify */
    protected function syncLocalVariantsFromResponse(Product $p, array
    $shopifyProduct): void
    {
        $variants = $shopifyProduct['variants'] ?? [];
        if (!$variants) return;
        $locals = $p->variants()->get()->all();
        $findLocal = function (array $sv) use ($locals) {
            foreach ($locals as $lv) {
                $matchSku = $lv->sku && isset($sv['sku']) && (string)$lv->sku
                    === (string)$sv['sku'];
                $matchO1 = (string)($lv->option1_value ?? '') === (string)
                ($sv['option1'] ?? '');
                $matchO2 = (string)($lv->option2_value ?? '') === (string)
                ($sv['option2'] ?? '');
                $matchO3 = (string)($lv->option3_value ?? '') === (string)
                ($sv['option3'] ?? '');
                if ($matchSku || ($matchO1 && $matchO2 && $matchO3)) return $lv;
            }
            return null;
        };
        foreach ($variants as $sv) {
            $lv = $findLocal($sv);
            if ($lv) {
                $lv->shopify_variant_id = $sv['id'] ?? $lv->shopify_variant_id;
                $lv->shopify_inventory_item_id = $sv['inventory_item_id'] ??
                    $lv->shopify_inventory_item_id;
                if (isset($sv['price'])) $lv->price = is_numeric($sv['price']) ? (float)$sv['price'] : null;
                if (isset($sv['inventory_quantity'])) $lv->inventory_quantity =
                    (int)$sv['inventory_quantity'];
                $lv->saveQuietly();
            } else {
                $p->variants()->create([
                    'title' => $sv['title'] ?? null,
                    'option1_value' => $sv['option1'] ?? null,
                    'option2_value' => $sv['option2'] ?? null,
                    'option3_value' => $sv['option3'] ?? null,
                    'sku' => $sv['sku'] ?? null,
                    'price' => isset($sv['price']) ?
                        (string)$sv['price'] : null,
                    'inventory_quantity' => $sv['inventory_quantity'] ??
                        0,
                    'shopify_variant_id' => $sv['id'] ?? null,
                    'shopify_inventory_item_id' => $sv['inventory_item_id'] ??
                        null,
                ]);
            }
        }
    }
    /** Sinkron stok per variant ke Shopify Inventory API */
    protected function pushInventoryLevels(Product $p): void
    {
        try {
            $inv = app(\App\Services\ShopifyInventoryService::class);
            $loc = $inv->getDefaultLocationId(); // <-- gunakan lokasi default (Setting/ENV/primary)
            foreach ($p->variants as $v) {
                if ($v->shopify_inventory_item_id) {
                    $inv->setInventory((int)$v->shopify_inventory_item_id, (int)$v->inventory_quantity, $loc);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[Inventory Sync] ' . $e->getMessage());
        }
    }
    /** HTTP wrapper */
    protected function request(string $method, string $uri, array $options =
    [], ?array &$statusOut = null): string
    {
        try {
            $resp = $this->http->request($method, $uri, $options);
            $code = $resp->getStatusCode();
            $body = (string) $resp->getBody();
            if (is_array($statusOut)) $statusOut['code'] = $code;
            if ($code >= 200 && $code < 300) return $body;
            $msg = $this->shorten("HTTP {$code} {$uri} :: " . $body);
            throw new ShopifyHttpException($code, $msg, $body);
        } catch (RequestException $e) {
            throw new ShopifyHttpException(0, 'HTTP error: ' . $e->getMessage());
        }
    }
    protected function shorten(string $s, int $len = 400): string
    {
        $s = trim($s);
        return mb_strlen($s) > $len ? (mb_substr($s, 0, $len) . '…') : $s;
    }
    protected function log(
        string $job,
        string $action,
        Product $p,
        ?int $http,
        string $status,
        string $msg,
        array $ctx = []
    ): void {
        try {
            SyncLog::create([
                'job' => $job,
                'action' => $action,
                'product_id' => $p->id,
                'shopify_product_id' => $p->shopify_product_id,
                'http_status' => $http,
                'status' => $status,
                'message' => $msg,
                'context' => $ctx ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SyncLog] failed: ' . $e->getMessage());
        }
    }
}
