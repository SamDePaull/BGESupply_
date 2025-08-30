<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Services\ShopifyPushService;

class PushInventoryCommand extends Command
{
    protected $signature = 'shopify:push-inventory {product_id?}';
    protected $description = 'Push inventory levels ke Shopify untuk satu produk (atau semua jika tidak diberi ID).';

    public function handle(ShopifyPushService $svc): int
    {
        $pid = $this->argument('product_id');

        if ($pid) {
            $p = Product::with('variants')->findOrFail($pid);
            $svc->pushInventoryLevels($p);
            $this->info("Pushed inventory for product #{$p->id}.");
            return self::SUCCESS;
        }

        Product::with('variants')->chunk(50, function ($chunk) use ($svc) {
            foreach ($chunk as $p) {
                $svc->pushInventoryLevels($p);
                $this->line("Pushed inventory for product #{$p->id}");
            }
        });

        $this->info('Done.');
        return self::SUCCESS;
    }
}
