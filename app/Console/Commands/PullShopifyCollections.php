<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyInventoryService;

class PullShopifyCollections extends Command
{
    protected $signature = 'shopify:pull-collections';
    protected $description = 'Pull Shopify custom & smart collections into local collections table';

    public function handle(ShopifyInventoryService $svc): int
    {
        try {
            $n = $svc->pullCollectionsIntoDb();
            $this->info("Imported/updated {$n} collections.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
