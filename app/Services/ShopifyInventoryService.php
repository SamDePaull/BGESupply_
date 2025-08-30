<?php

namespace App\Services;

use App\Exceptions\ShopifyHttpException;
use App\Models\Category;

use App\Models\Collection as ShopCollection;
use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ShopifyInventoryService
{
    protected Client $http;
    protected string $base;
    protected string $version;
    protected string $shop;
    public function __construct()
    {
        $this->shop = trim(config('services.shopify.shop', ''));
        $this->version = trim(config('services.shopify.version', '2025-07'));
        $token = trim(config('services.shopify.token', ''));
        if ($this->shop === '' || $token === '') {
            throw new \RuntimeException('Set SHOPIFY_SHOP & SHOPIFY_TOKEN');
        }
        if (Str::contains($this->shop, '.')) {
            $this->shop = explode('.', $this->shop)[0];
        }
        $this->base = "https://{$this->shop}.myshopify.com/admin/api/{$this->version}/";
        $this->http = new Client(['base_uri' => $this->base, 'headers' => ['X-Shopify-Access-Token' => $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json'], 'http_errors' => false, 'timeout' => 30]);
    }
    public function getShopInfo(): array
    {
        $json = $this->request('GET', 'shop.json');
        $d = json_decode($json, true);
        return $d['shop'] ?? [];
    }
    public function listLocations(): array
    {
        $json = $this->request('GET', 'locations.json');
        $d = json_decode($json, true);
        return $d['locations'] ?? [];
    }
    public function getPrimaryLocationId(): int
    {
        $shop = $this->getShopInfo();
        return (int)($shop['primary_location_id'] ?? 0);
    }
    public function getDefaultLocationId(): int
    {
        $val = Setting::get('shopify.location_id');
        if (is_array($val) && isset($val['id'])) return (int)$val['id'];
        if (is_numeric($val)) return (int)$val;
        $env = (int)env('SHOPIFY_LOCATION_ID', 0);
        if ($env > 0) return $env;
        $primary = $this->getPrimaryLocationId();
        if ($primary > 0) return $primary;
        foreach ($this->listLocations() as $loc) {
            if (!empty($loc['active'])) return (int)$loc['id'];
        }
        $all = $this->listLocations();
        if (!empty($all)) return (int)$all[0]['id'];
        throw new \RuntimeException('No Shopify locations found');
    }

    protected function request(string $method, string $uri, array $options = []): string
    {
        try {
            $resp = $this->http->request($method, $uri, $options);
            $code = $resp->getStatusCode();
            $body = (string)$resp->getBody();
            if ($code >= 200 && $code < 300) return $body;
            throw new ShopifyHttpException($code, "HTTP {$code} {$uri} :: " . trim($body), $body);
        } catch (RequestException $e) {
            throw new ShopifyHttpException(0, 'HTTP error: ' . $e->getMessage());
        }
    }

    /** Coba GraphQL (productTypes), lalu fallback REST (products.product_type). */
    public function listProductTypes(): array
    {
        // 1) GraphQL: productTypes
        try {
            $payload = ['query' => 'query { productTypes(first: 250) { edges { node } } }'];
            $json = $this->request('POST', 'graphql.json', ['json' => $payload]);
            $d = json_decode($json, true);
            $types = array_map(fn($e) => (string)($e['node'] ?? ''), $d['data']['productTypes']['edges'] ?? []);
            $types = array_values(array_filter(array_unique($types)));
            if (!empty($types)) {
                sort($types);
                return $types;
            }
        } catch (\Throwable $e) {
            // lanjut fallback
        }

        // 2) REST fallback: kumpulkan product_type dari /products.json (paging by since_id)
        $types = [];
        $sinceId = 0;
        for ($page = 0; $page < 10; $page++) { // batasi 10 halaman agar aman
            $query = $sinceId > 0 ? ['since_id' => $sinceId, 'limit' => 250, 'fields' => 'id,product_type'] : ['limit' => 250, 'fields' => 'id,product_type'];
            $qs = http_build_query($query);
            $json = $this->request('GET', "products.json?{$qs}");
            $d = json_decode($json, true);
            $items = $d['products'] ?? [];
            if (empty($items)) break;

            foreach ($items as $p) {
                $types[] = (string)($p['product_type'] ?? '');
                $id = (int)($p['id'] ?? 0);
                if ($id > $sinceId) $sinceId = $id;
            }
        }
        $types = array_values(array_filter(array_unique($types)));
        sort($types);
        return $types;
    }

    /** Simpan ke tabel categories (name + shopify_category). */
    public function pullCategoriesIntoDb(): int
    {
        $types = $this->listProductTypes();
        $count = 0;
        foreach ($types as $name) {
            Category::firstOrCreate(['name' => $name], ['shopify_category' => $name]);
            $count++;
        }
        return $count;
    }

    /** Ambil smart & custom collections dari REST. */
    public function listCollections(): array
    {
        $all = [];

        // smart_collections
        $page = 0;
        $sinceId = 0;
        do {
            $qs = http_build_query(['limit' => 250] + ($sinceId ? ['since_id' => $sinceId] : []));
            $json = $this->request('GET', "smart_collections.json?{$qs}");
            $d = json_decode($json, true);
            $items = $d['smart_collections'] ?? [];
            foreach ($items as $c) {
                $all[] = [
                    'id' => (int)($c['id'] ?? 0),
                    'title' => (string)($c['title'] ?? ''),
                    'handle' => (string)($c['handle'] ?? ''),
                    'type' => 'smart',
                    'body_html' => $c['body_html'] ?? null,
                    'published_at' => $c['published_at'] ?? null,
                ];
                $sinceId = max($sinceId, (int)($c['id'] ?? 0));
            }
            $page++;
        } while (!empty($items) && $page < 10);

        // custom_collections
        $page = 0;
        $sinceId = 0;
        do {
            $qs = http_build_query(['limit' => 250] + ($sinceId ? ['since_id' => $sinceId] : []));
            $json = $this->request('GET', "custom_collections.json?{$qs}");
            $d = json_decode($json, true);
            $items = $d['custom_collections'] ?? [];
            foreach ($items as $c) {
                $all[] = [
                    'id' => (int)($c['id'] ?? 0),
                    'title' => (string)($c['title'] ?? ''),
                    'handle' => (string)($c['handle'] ?? ''),
                    'type' => 'custom',
                    'body_html' => $c['body_html'] ?? null,
                    'published_at' => $c['published_at'] ?? null,
                ];
                $sinceId = max($sinceId, (int)($c['id'] ?? 0));
            }
            $page++;
        } while (!empty($items) && $page < 10);

        return $all;
    }

    /** Simpan collections ke DB. */
    public function pullCollectionsIntoDb(): int
    {
        $all = $this->listCollections();
        $n = 0;
        foreach ($all as $c) {
            ShopCollection::updateOrCreate(
                ['shopify_collection_id' => $c['id']],
                [
                    'title' => $c['title'],
                    'handle' => $c['handle'],
                    'type' => $c['type'],
                    'body_html' => $c['body_html'],
                    'published_at' => $c['published_at'],
                ]
            );
            $n++;
        }
        return $n;
    }

    public function getLocationName(int $locationId): string
    {
        foreach ($this->listLocations() as $l) {
            if ((int)($l['id'] ?? 0) === (int)$locationId) {
                return (string)($l['name'] ?? (string)$locationId);
            }
        }
        return (string)$locationId;
    }

    /** Pastikan item terhubung ke lokasi (REST). Aman jika sudah connect. */
    public function ensureInventoryLevelConnected(int $inventoryItemId, int $locationId): void
    {
        $payload = [
            'location_id'       => $locationId,
            'inventory_item_id' => $inventoryItemId,
            'relocate_if_necessary' => true,
        ];
        $json = $this->request('POST', 'inventory_levels/connect.json', ['json' => $payload]);
        $arr  = json_decode($json, true);
        if (isset($arr['errors']) && $arr['errors']) {
            $msg = is_string($arr['errors']) ? $arr['errors'] : json_encode($arr['errors']);
            if (stripos($msg, 'already') === false) {
                throw new \RuntimeException("inventory_levels/connect failed: {$msg}");
            }
        }
    }

    /** Aktivasi item pada lokasi (GraphQL). Aman jika sudah aktif. */
    public function inventoryActivate(int $inventoryItemId, int $locationId): void
    {
        $gidItem = "gid://shopify/InventoryItem/{$inventoryItemId}";
        $gidLoc  = "gid://shopify/Location/{$locationId}";
        $gql = <<<'GQL'
mutation Act($input: InventoryActivateInput!) {
  inventoryActivate(input: $input) {
    inventoryLevel { id }
    userErrors { field message }
  }
}
GQL;
        $resp = $this->request('POST', 'graphql.json', ['json' => ['query' => $gql, 'variables' => ['input' => ['inventoryItemId' => $gidItem, 'locationId' => $gidLoc]]]]);
        $data = json_decode($resp, true);
        $errs = $data['errors'] ?? [];
        $uErr = Arr::get($data, 'data.inventoryActivate.userErrors', []);
        if (!empty($errs) || !empty($uErr)) {
            $msg = implode('; ', array_map(fn($e) => $e['message'] ?? 'error', $uErr ?? []));
            if ($msg && stripos($msg, 'already') === false) {
                throw new \RuntimeException("inventoryActivate failed: {$msg}");
            }
        }
    }

    /** Ambil angka available pada lokasi untuk verifikasi */
    public function getInventoryAvailable(int $inventoryItemId, int $locationId = 0): ?int
    {
        $locationId = $locationId ?: $this->getDefaultLocationId();
        $qs = http_build_query([
            'inventory_item_ids' => $inventoryItemId,
            'location_ids'       => $locationId,
            'limit'              => 1,
        ]);
        $json = $this->request('GET', "inventory_levels.json?{$qs}");
        $arr  = json_decode($json, true);
        $lvl  = $arr['inventory_levels'][0] ?? null;
        return $lvl['available'] ?? null;
    }

    public function getInventoryTracked(int $inventoryItemId): ?bool
    {
        $gidItem = "gid://shopify/InventoryItem/{$inventoryItemId}";
        $q = <<<'GQL'
query Q($id: ID!){
  inventoryItem(id:$id){ id tracked }
}
GQL;
        $resp = $this->request('POST', 'graphql.json', ['json' => ['query' => $q, 'variables' => ['id' => $gidItem]]]);
        $data = json_decode($resp, true);
        if (!empty($data['errors'])) {
            // kalau token kurang scope read_inventory, kita dapat error di sini
            throw new \RuntimeException('inventoryItem query failed: ' . json_encode($data['errors']));
        }
        return Arr::get($data, 'data.inventoryItem.tracked');
    }

    /** Set tracked=true hanya jika perlu, dan tampilkan userErrors biar jelas */
    public function setInventoryTracked(int $inventoryItemId, bool $tracked = true): void
    {
        $cur = $this->getInventoryTracked($inventoryItemId);
        if ($cur === true && $tracked === true) return;

        $gidItem = "gid://shopify/InventoryItem/{$inventoryItemId}";
        $m = <<<'GQL'
mutation U($id: ID!, $input: InventoryItemUpdateInput!){
  inventoryItemUpdate(id:$id, input:$input){
    inventoryItem{ id tracked }
    userErrors{ field message }
  }
}
GQL;
        $resp = $this->request('POST', 'graphql.json', ['json' => [
            'query' => $m,
            'variables' => ['id' => $gidItem, 'input' => ['tracked' => $tracked]],
        ]]);
        $data = json_decode($resp, true);
        $errs = $data['errors'] ?? [];
        $uErr = Arr::get($data, 'data.inventoryItemUpdate.userErrors', []);
        if (!empty($errs) || !empty($uErr)) {
            $msg = 'inventoryItemUpdate failed';
            if (!empty($errs)) $msg .= ': ' . json_encode($errs);
            if (!empty($uErr)) $msg .= ': ' . implode('; ', array_map(fn($e) => $e['message'] ?? 'error', $uErr));
            throw new \RuntimeException($msg);
        }
    }

    /** Set stok pakai mutasi modern: inventorySetQuantities (butuh write_inventory) */
    public function setInventory(int $inventoryItemId, int $available, int $locationId = 0): void
    {
        $locationId = $locationId ?: $this->getDefaultLocationId();

        // pastikan tracked + aktif di lokasi (connect/activate mu sudah oke; panggil kalau kamu punya helpernya)
        $this->setInventoryTracked($inventoryItemId, true);
        $this->ensureInventoryLevelConnected($inventoryItemId, $locationId);
        $this->inventoryActivate($inventoryItemId, $locationId);

        $gidItem = "gid://shopify/InventoryItem/{$inventoryItemId}";
        $gidLoc  = "gid://shopify/Location/{$locationId}";

        $m = <<<'GQL'
mutation SetQty($input: InventorySetQuantitiesInput!){
  inventorySetQuantities(input:$input){
    inventoryAdjustmentGroup{
      reason
      changes{ name delta }
    }
    userErrors{ field message }
  }
}
GQL;

        $variables = [
            'input' => [
                'name' => 'available',
                'reason' => 'correction',
                'ignoreCompareQuantity' => true, // sederhanakan dulu
                'quantities' => [[
                    'inventoryItemId' => $gidItem,
                    'locationId'      => $gidLoc,
                    'quantity'        => (int)$available,
                ]],
            ],
        ];

        $resp = $this->request('POST', 'graphql.json', ['json' => ['query' => $m, 'variables' => $variables]]);
        $data = json_decode($resp, true);
        $errs = $data['errors'] ?? [];
        $uErr = Arr::get($data, 'data.inventorySetQuantities.userErrors', []);
        if (!empty($errs) || !empty($uErr)) {
            $msg = 'inventorySetQuantities failed';
            if (!empty($errs)) $msg .= ': ' . json_encode($errs);
            if (!empty($uErr)) $msg .= ': ' . implode('; ', array_map(fn($e) => $e['message'] ?? 'error', $uErr));
            throw new \RuntimeException($msg);
        }
    }
}
