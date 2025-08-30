<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Services\ShopifyInventoryService;

class InventoryDoctorCommand extends Command
{
    protected $signature = 'inventory:doctor {product_id}';
    protected $description = 'Diagnosa & push stok ke Shopify untuk product_id tertentu (per varian), lengkap dengan verifikasi.';

    public function handle(ShopifyInventoryService $inv): int
    {
        $pid = (int)$this->argument('product_id');
        $p = Product::with('variants')->findOrFail($pid);

        $loc = $inv->getDefaultLocationId();
        $locName = $inv->getLocationName($loc);
        $this->info("Location: {$locName} ({$loc})");

        foreach ($p->variants as $v) {
            $ii = (int)($v->shopify_inventory_item_id ?? 0);
            $qty = is_numeric($v->inventory_quantity) ? (int)$v->inventory_quantity : (int)($p->inventory_quantity ?? 0);

            $this->line("Var#{$v->id} inv_item={$ii} will_push_qty={$qty}");

            if ($ii <= 0) {
                $this->warn("  -> SKIP (inventory_item_id kosong)");
                continue;
            }

            try {
                $inv->setInventory($ii, $qty, $loc);
                $after = $inv->getInventoryAvailable($ii, $loc);
                $this->info("  -> OK set qty={$qty}; verified_available={$after}");
            } catch (\Throwable $e) {
                $this->error("  -> FAIL: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
