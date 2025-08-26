<?php

namespace App\Console\Commands;

use App\Services\ShopifyService;
use Illuminate\Console\Command;

class ShopifyPullCommand extends Command
{
    protected $signature = 'shopify:pull {--limit=0 : Batasi jumlah pulled (0 = semua)}';
    protected $description = 'Tarik semua produk dari Shopify dan ingest ke DB (sinkron, terlihat errornya)';

    public function handle(ShopifyService $svc): int
    {
        try {
            $count = $svc->pullAndIngest((int) $this->option('limit'));
            $this->info("✔ Pulled $count product(s)");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✖ Pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
