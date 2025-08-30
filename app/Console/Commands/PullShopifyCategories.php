<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyInventoryService;

class PullShopifyCategories extends Command
{
    // HAPUS opsi --verbose di signature (cukup nama command saja)
    protected $signature = 'shopify:pull-categories';
    protected $description = 'Pull product types from Shopify into local categories table';

    public function handle(ShopifyInventoryService $svc): int
    {
        try {
            $n = $svc->pullCategoriesIntoDb();

            // Gunakan verbose bawaan: -v / -vv / -vvv
            if ($this->output->isVerbose() && $n === 0) {
                $this->warn('0 categories from GraphQL. Trying REST fallback inspection...');
                $types = $svc->listProductTypes();
                $this->line('Detected types (unique): ' . json_encode($types, JSON_UNESCAPED_UNICODE));
            }

            $this->info("Imported/updated {$n} categories.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
