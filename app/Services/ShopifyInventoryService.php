<?php

namespace App\Services;

use App\Exceptions\ShopifyHttpException;
use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;

class ShopifyInventoryService
{
    protected Client $http;
    protected string $base;
    protected string $version;
    protected string $shop;

    public function __construct()
    {
        $this->shop    = trim(config('services.shopify.shop', ''));
        $this->version = trim(config('services.shopify.version', '2024-07'));
        $token         = trim(config('services.shopify.token', ''));

        if ($this->shop === '' || $token === '') {
            throw new \RuntimeException('Shopify config missing: set SHOPIFY_SHOP & SHOPIFY_TOKEN in .env');
        }
        if (Str::contains($this->shop, '.')) {
            $this->shop = explode('.', $this->shop)[0];
        }

        $this->base = "https://{$this->shop}.myshopify.com/admin/api/{$this->version}/";
        $this->http = new Client([
            'base_uri' => $this->base,
            'headers'  => [
                'X-Shopify-Access-Token' => $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }

    /** GET /shop.json — berisi primary_location_id */
    public function getShopInfo(): array
    {
        $json = $this->request('GET', 'shop.json');
        $data = json_decode($json, true);
        return $data['shop'] ?? [];
    }

    /** ID lokasi utama dari shop.json (0 jika tak ada) */
    public function getPrimaryLocationId(): int
    {
        $shop = $this->getShopInfo();
        return (int) ($shop['primary_location_id'] ?? 0);
    }

    /** Daftar lokasi aktif */
    public function listLocations(): array
    {
        $json = $this->request('GET', 'locations.json');
        $data = json_decode($json, true);
        return $data['locations'] ?? [];
    }

    /**
     * Default location ID (urutan prioritas):
     * 1) Setting DB (Settings → Shopify Settings)
     * 2) ENV SHOPIFY_LOCATION_ID
     * 3) shop.json → primary_location_id
     * 4) lokasi aktif pertama
     */
    public function getDefaultLocationId(): int
    {
        // 1) Setting DB
        $val = Setting::get('shopify.location_id');
        if (is_array($val) && isset($val['id'])) return (int) $val['id'];
        if (is_numeric($val)) return (int) $val;

        // 2) ENV
        $env = (int) env('SHOPIFY_LOCATION_ID', 0);
        if ($env > 0) return $env;

        // 3) Primary dari shop.json
        $primary = $this->getPrimaryLocationId();
        if ($primary > 0) return $primary;

        // 4) Fallback: lokasi aktif pertama
        foreach ($this->listLocations() as $loc) {
            if (!empty($loc['active'])) return (int) $loc['id'];
        }
        $all = $this->listLocations();
        if (!empty($all)) return (int) $all[0]['id'];

        throw new \RuntimeException('No Shopify locations found. Ensure your store has at least one active location.');
    }

    /** Set stok absolut pada lokasi tertentu */
    public function setInventory(int $inventoryItemId, int $available, int $locationId = 0): void
    {
        $locationId = $locationId ?: $this->getDefaultLocationId();
        $payload = [
            'location_id'        => $locationId,
            'inventory_item_id'  => $inventoryItemId,
            'available'          => $available,
        ];
        $this->request('POST', 'inventory_levels/set.json', ['json' => $payload]);
    }

    /** Helper request */
    protected function request(string $method, string $uri, array $options = []): string
    {
        try {
            $resp = $this->http->request($method, $uri, $options);
            $code = $resp->getStatusCode();
            $body = (string) $resp->getBody();
            if ($code >= 200 && $code < 300) return $body;
            throw new ShopifyHttpException($code, "HTTP {$code} {$uri} :: " . trim($body), $body);
        } catch (RequestException $e) {
            throw new ShopifyHttpException(0, 'HTTP error: ' . $e->getMessage());
        }
    }
}
