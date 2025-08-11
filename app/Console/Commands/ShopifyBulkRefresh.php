<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Services\ShopifyService;

class ShopifyBulkRefresh extends Command
{
    protected $signature = 'shopify:bulk-refresh {--only= : shopify|offline|all}';
    protected $description = 'Refresh data produk dari Shopify untuk semua produk origin=shopify';

    public function handle(ShopifyService $svc): int
    {
        $only = $this->option('only') ?: 'shopify';

        $query = Product::query();
        if ($only === 'shopify') $query->where('origin', 'shopify')->whereNotNull('shopify_product_id');

        $count = 0; $fail = 0;
        $query->chunkById(200, function ($chunk) use ($svc, &$count, &$fail) {
            foreach ($chunk as $p) {
                $ok = $svc->pullSingleProductAndIngest((int) $p->shopify_product_id);
                $ok ? $count++ : $fail++;
            }
        });

        $this->info("Bulk Refresh done: {$count} sukses, {$fail} gagal");
        return self::SUCCESS;
    }
}
