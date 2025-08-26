<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use App\Services\ShopifyInventoryService;

class ShopStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';
    protected function getCards(): array
    {
        $products = Product::count();
        $variants = ProductVariant::count();
        $failed = SyncLog::where('status', 'failed')->count();
        $location = 'N/A';
        try {
            $locId = app(ShopifyInventoryService::class)->getDefaultLocationId();
            $location = (string) $locId;
        } catch (\Throwable $e) {
        }
        return [
            Stat::make('Products', number_format($products))->description('Total'),
            Stat::make('Variants', number_format($variants))->description('Total'),
            Stat::make('Failed sync', number_format($failed))->description('All time'),
            Stat::make('Default Location', $location)->description('Shopify'),
        ];
    }
}
