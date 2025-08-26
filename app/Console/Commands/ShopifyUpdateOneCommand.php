<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ShopifyPushService;
use Illuminate\Console\Command;

class ShopifyUpdateOneCommand extends Command
{
    protected $signature = 'shopify:update-one {product_id}';
    protected $description = 'Update satu produk ke Shopify (langsung, sinkron, untuk debug)';

    public function handle(ShopifyPushService $svc): int
    {
        $id = (int) $this->argument('product_id');
        $p  = Product::find($id);
        if (!$p) {
            $this->error("Product #{$id} not found");
            return self::FAILURE;
        }

        try {
            $svc->updateUnifiedToShopify($p);
            $this->info("OK updated product #{$p->id} (shopify={$p->shopify_product_id})");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('FAIL: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
