<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ShopifyPushService;
use App\Services\ShopifyInventoryService;
use Illuminate\Console\Command;

class CatalogRepairCommand extends Command
{
    protected $signature = 'catalog:repair {--ids=} {--push-inventory}';
    protected $description = 'Backfill harga varian & (opsional) push stok ke Shopify';
    public function handle(ShopifyPushService $push, ShopifyInventoryService $inv): int
    {
        $ids = $this->option('ids');
        $q = Product::with('variants');
        if ($ids) {
            $q->whereIn('id', array_map('intval', explode(',', $ids)));
        }
        $count = 0;
        $q->chunkById(100, function ($chunk) use (&$count) {
            foreach ($chunk as $p) {
                $p->applyVariantPriceFallback();
                $count++;
            }
        });
        $this->info("Updated variant prices for {$count} products.");
        if ($this->option('push-inventory')) {
            $q = Product::with('variants')->whereNotNull('shopify_product_id');
            $q->chunkById(100, function ($chunk) use ($push) {
                foreach ($chunk as $p) {
                    $push->pushInventoryLevels($p);
                }
            });
            $this->info('Inventory pushed.');
        }
        return self::SUCCESS;
    }
}
