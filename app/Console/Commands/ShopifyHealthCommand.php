<?php

namespace App\Console\Commands;

use App\Services\ShopifyService;
use Illuminate\Console\Command;

class ShopifyHealthCommand extends Command
{
    protected $signature = 'shopify:health';
    protected $description = 'Cek koneksi & kredensial Shopify (shop.json)';

    public function handle(ShopifyService $svc): int
    {
        try {
            $info = $svc->healthCheck(); // GET shop.json
            $this->info('✔ Shopify connected');
            $this->line('Shop: ' . $info['shop']['name'] . ' (' . $info['shop']['myshopify_domain'] . ')');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✖ Shopify health failed: ' . $e->getMessage());
            $this->newLine();
            $this->line('Cek .env: SHOPIFY_SHOP (subdomain saja), SHOPIFY_TOKEN, SHOPIFY_API_VERSION');
            $this->line('Pastikan token punya scope: read_products (min), write_products untuk push.');
            return self::FAILURE;
        }
    }
}
