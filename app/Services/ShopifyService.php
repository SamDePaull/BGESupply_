<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ShopifyProductStaging;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopifyService
{
    protected Client $http;
    protected string $base;
    protected string $version;
    protected string $shop;

    public function __construct()
    {
        $this->shop    = trim(config('services.shopify.shop', ''));
        // Samakan default versi API
        $this->version = trim(config('services.shopify.version', '2025-07'));
        $token         = trim(config('services.shopify.token', ''));

        if ($this->shop === '' || $token === '') {
            throw new \RuntimeException('Shopify config missing. Pastikan values di config/services.php (env: SHOPIFY_*).');
        }
        // SHOPIFY_SHOP harus subdomain saja (tanpa .myshopify.com)
        if (Str::contains($this->shop, '.')) {
            $parts = explode('.', $this->shop);
            $this->shop = $parts[0];
        }

        $this->base = "https://{$this->shop}.myshopify.com/admin/api/{$this->version}/";

        $this->http = new Client([
            'base_uri'    => $this->base,
            'headers'     => [
                'X-Shopify-Access-Token' => $token,
                'Accept'                 => 'application/json',
                'Content-Type'           => 'application/json',
            ],
            'http_errors' => false,
            'timeout'     => 30,
        ]);
    }

    /** Quick health check: GET shop.json */
    public function healthCheck(): array
    {
        $body = $this->request('GET', 'shop.json');
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    /** Pull + ingest semua produk. Bisa dibatasi $limit. */
    public function pullAndIngest(int $limit = 0): int
    {
        $count    = 0;
        $endpoint = 'products.json?limit=250';
        $status   = null;

        do {
            $json  = $this->request('GET', $endpoint, [], $status);
            $data  = json_decode($json, true);
            $items = $data['products'] ?? [];

            foreach ($items as $p) {
                $this->ingestShopifyProduct($p);
                $count++;
                if ($limit > 0 && $count >= $limit) {
                    return $count;
                }
            }

            $endpoint = $this->nextLinkFromHeader($status['link'] ?? null);
        } while ($endpoint);

        return $count;
    }

    /** Pull single product by id & ingest. */
    public function pullSingleProductAndIngest(?int $shopifyProductId): void
    {
        if (!$shopifyProductId) return;
        $json = $this->request('GET', "products/{$shopifyProductId}.json");
        $data = json_decode($json, true);
        if (isset($data['product'])) {
            $this->ingestShopifyProduct($data['product']);
        }
    }

    /** HTTP wrapper + pagination headers. */
    protected function request(string $method, string $uri, array $options = [], ?array &$statusOut = null): string
    {
        try {
            $resp    = $this->http->request($method, $uri, $options);
            $code    = $resp->getStatusCode();
            $headers = [
                'link'       => $resp->getHeaderLine('Link'),
                'ratelimit'  => $resp->getHeaderLine('X-Shopify-Shop-Api-Call-Limit'),
                'retryafter' => $resp->getHeaderLine('Retry-After'),
            ];
            $statusOut = ['code' => $code] + $headers;

            $body = (string) $resp->getBody();

            if ($code >= 200 && $code < 300) {
                return $body;
            }

            if ($code === 401 || $code === 403) {
                throw new \RuntimeException("Shopify auth/scope error {$code}. Cek token & scopes (read_products). Body: " . $this->trim($body));
            }
            if ($code === 404) {
                throw new \RuntimeException("Shopify endpoint not found (404): {$uri}. Cek API_VERSION & URL.");
            }
            if ($code === 429) {
                $retry = $headers['retryafter'] ?: 'N/A';
                throw new \RuntimeException("Rate limited (429). Retry-After={$retry}. Body: " . $this->trim($body));
            }
            if ($code >= 500) {
                throw new \RuntimeException("Shopify server error {$code}. Body: " . $this->trim($body));
            }

            throw new \RuntimeException("Shopify error {$code}. Body: " . $this->trim($body));
        } catch (RequestException $e) {
            throw new \RuntimeException('HTTP error: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function trim(string $state, int $len = 400): string
    {
        $state = trim($state);
        return mb_strlen($state) > $len ? (mb_substr($state, 0, $len) . '…') : $state;
    }

    /** Parse Link header untuk cursor pagination. */
    protected function nextLinkFromHeader(?string $linkHeader): ?string
    {
        if (!$linkHeader) return null;
        foreach (explode(',', $linkHeader) as $part) {
            if (str_contains($part, 'rel="next"')) {
                if (preg_match('/<([^>]+)>;\s*rel="next"/i', trim($part), $m)) {
                    $url    = $m[1];
                    $needle = '/admin/api/' . $this->version . '/';
                    $pos    = strpos($url, $needle);
                    return $pos !== false ? substr($url, $pos + strlen($needle)) : $url;
                }
            }
        }
        return null;
    }

    /** Ingest ke schema lokal (plus isi staging & gambar). */
    protected function ingestShopifyProduct(array $payload): void
    {
        $variants = $payload['variants'] ?? [];
        $first    = $variants[0] ?? [];

        /* 1) Simpan payload penuh ke STAGING (agar halaman Shopify Staging terisi) */
        ShopifyProductStaging::updateOrCreate(
            ['shopify_product_id' => (int) ($payload['id'] ?? 0)],
            [
                'handle'  => $payload['handle'] ?? null,
                'title'   => $payload['title'] ?? null,
                'payload' => $payload,
                'status'  => 'pulled',
            ]
        );

        $tagsRaw  = $payload['tags'] ?? null;
        $tags     = is_array($tagsRaw) ? implode(',', $tagsRaw) : $tagsRaw;

        // Nama opsi produk (maks 3)
        $optNames = [null, null, null];
        if (!empty($payload['options'])) {
            foreach ($payload['options'] as $i => $opt) {
                if ($i > 2) break;
                $optNames[$i] = $opt['name'] ?? null;
            }
        }

        // Harga minimal antar varian → price produk
        $allPrices = array_values(array_filter(array_map(
            fn ($v) => isset($v['price']) ? (float) $v['price'] : null,
            $variants
        )));
        $minPrice = !empty($allPrices)
            ? min($allPrices)
            : (isset($first['price']) ? (float) $first['price'] : 0);

        // Status & published_at
        $statusFromPayload = $payload['status'] ?? null; // active|draft|archived
        $publishedAtStr    = $payload['published_at'] ?? null;
        $publishedAt       = $publishedAtStr ? Carbon::parse($publishedAtStr) : null;
        $computedStatus    = $statusFromPayload ?: ($publishedAt ? 'active' : 'draft');

        $sku = $first['sku'] ?? null;

        /* 2) Category mapping (anti-duplikasi, case-insensitive) */
        $categoryId  = null;
        $productType = $payload['product_type'] ?? null;
        if ($productType && trim($productType) !== '') {
            $needle   = mb_strtolower(trim($productType));
            $existing = Category::whereRaw('LOWER(name) = ?', [$needle])->first();
            if (!$existing) {
                $existing = Category::create([
                    'name'             => trim($productType),
                    'shopify_category' => $productType,
                ]);
            }
            $categoryId = $existing->id;
        }

        /* 3) Upsert ke tabel products */
        $row = [
            'title'              => $payload['title'] ?? null,
            'handle'             => $payload['handle'] ?? null,
            'description'        => $payload['body_html'] ?? null,
            'vendor'             => $payload['vendor'] ?? null,
            'product_type'       => $productType,
            'category_id'        => $categoryId,
            'tags'               => $tags,
            'sku'                => $sku,
            'price'              => number_format($minPrice, 2, '.', ''),
            'compare_at_price'   => isset($first['compare_at_price']) ? (string) $first['compare_at_price'] : null,
            'inventory_quantity' => $first['inventory_quantity'] ?? 0,
            'shopify_product_id' => $payload['id'] ?? null,
            'status'             => $computedStatus,
            'published_at'       => $publishedAt,
            'sync_status'        => 'synced',
            'last_synced_at'     => now(),
            'updated_at'         => now(),
            'created_at'         => now(),
            'option1_name'       => $optNames[0],
            'option2_name'       => $optNames[1],
            'option3_name'       => $optNames[2],
        ];

        DB::beginTransaction();
        try {
            if ($sku) {
                DB::table('products')->updateOrInsert(['sku' => $sku], $row);
            } else {
                DB::table('products')->updateOrInsert(['shopify_product_id' => $payload['id'] ?? null], $row);
            }

            // Ambil product lokal
            $p = Product::where('shopify_product_id', $payload['id'] ?? 0)->first();
            if (!$p && $sku) {
                $p = Product::where('sku', $sku)->first();
            }

            if ($p) {
                /* 4) Sinkron varian */
                foreach ($variants as $sv) {
                    $match = ProductVariant::where('product_id', $p->id)
                        ->where(function ($q) use ($sv) {
                            if (!empty($sv['sku'])) {
                                $q->orWhere('sku', $sv['sku']);
                            }
                            $q->orWhere(function ($qq) use ($sv) {
                                $qq->where('option1_value', $sv['option1'] ?? null)
                                   ->where('option2_value', $sv['option2'] ?? null)
                                   ->where('option3_value', $sv['option3'] ?? null);
                            });
                        })
                        ->first();

                    $vals = [
                        'title'                     => $sv['title'] ?? null,
                        'option1_value'             => $sv['option1'] ?? null,
                        'option2_value'             => $sv['option2'] ?? null,
                        'option3_value'             => $sv['option3'] ?? null,
                        'sku'                       => $sv['sku'] ?? null,
                        'price'                     => isset($sv['price']) ? (string) $sv['price'] : null,
                        'inventory_quantity'        => $sv['inventory_quantity'] ?? 0,
                        'shopify_variant_id'        => $sv['id'] ?? null,
                        'shopify_inventory_item_id' => $sv['inventory_item_id'] ?? null,
                        'updated_at'                => now(),
                    ];

                    if ($match) {
                        $match->fill($vals)->saveQuietly();
                    } else {
                        $p->variants()->create($vals + ['created_at' => now()]);
                    }
                }

                /* 5) Sinkron gambar Shopify → product_images (shopify_image_id + src) */
                $images = $payload['images'] ?? [];
                if (!empty($images)) {
                    foreach ($images as $img) {
                        $imgId    = isset($img['id']) ? (int) $img['id'] : null;
                        $position = $img['position'] ?? null;
                        $alt      = $img['alt'] ?? null;
                        $src      = $img['src'] ?? null; // URL Shopify

                        if (!$src) {
                            continue;
                        }

                        // Upsert by shopify_image_id bila ada; jika tidak, pakai kombinasi product_id + src
                        $cond = $imgId ? ['shopify_image_id' => $imgId, 'product_id' => $p->id]
                                       : ['product_id' => $p->id, 'file_path' => $src];

                        $data = [
                            'file_path'        => $src, // simpan URL Shopify
                            'alt'              => $alt,
                            'position'         => $position,
                            'is_primary'       => $position === 1 || ($position === null ? false : false),
                            'shopify_image_id' => $imgId,
                            'updated_at'       => now(),
                        ];

                        ProductImage::updateOrCreate($cond, $data + ['product_id' => $p->id]);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
