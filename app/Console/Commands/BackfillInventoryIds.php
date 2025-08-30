<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Services\ShopifyPushService;

class BackfillInventoryIds extends Command
{
    protected $signature = 'catalog:backfill-inventory-ids {product_id?}';
    protected $description = 'Sinkron ulang shopify_variant_id dan shopify_inventory_item_id dari Shopify.';

    public function handle(ShopifyPushService $push): int
    {
        $pid = $this->argument('product_id');

        $query = Product::query();
        if ($pid) $query->whereKey($pid);

        $query->chunkById(50, function ($chunk) use ($push) {
            foreach ($chunk as $p) {
                // pakai refresh yang robust
                $push->refreshVariantInventoryIds($p);
                // reload dan log ringkas
                $p->unsetRelation('variants'); $p->load('variants');
                $this->line("Product #{$p->id}: mapped ". $p->variants()->whereNotNull('shopify_inventory_item_id')->count() ." inv_items.");
            }
        });

        $this->info('Done.');
        return self::SUCCESS;
    }
}
