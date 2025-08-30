<?php

namespace App\Console\Commands;

use App\Services\ShopifyInventoryService;
use Illuminate\Console\Command;

class ShopifyLocationsCommand extends Command
{
    protected $signature = 'shopify:locations {--json}';
    protected $aliases = ['shopify:location'];
    protected $description = 'List Shopify locations & primary';
    public function handle(ShopifyInventoryService $svc): int
    {
        try {
            $locs = $svc->listLocations();
            $primaryId = $svc->getPrimaryLocationId();
            if ($this->option('json')) {
                $this->line(json_encode(['primary_location_id' => $primaryId, 'locations' => $locs], JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }
            foreach ($locs as $l) {
                $id = (int)($l['id'] ?? 0);
                $name = (string)($l['name'] ?? 'Unknown');
                $isPrimary = ($primaryId > 0 && $id === $primaryId);
                $flag = $isPrimary ? '* ' : '  ';
                $active = !empty($l['active']) ? 'active' : 'inactive';
                $this->line("{$flag}{$id} — {$name} ({$active})");
            }
            $this->line('Primary ditandai *  | Set default di ENV SHOPIFY_LOCATION_ID atau Filament → Shopify Settings');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
