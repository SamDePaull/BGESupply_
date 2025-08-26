<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use App\Models\Sale; // jika tidak ada model Sale, hapus bagian terkait
use App\Services\ShopifyInventoryService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class OpsOverview extends BaseWidget
{
    /**
     * Matikan polling untuk mencegah POST otomatis (potensi 419/CSRF saat dev/ ngrok)
     */
    protected static ?string $pollingInterval = null;
    protected int|string|array $columnSpan = 'full';
    protected function getStats(): array
    {
        // ====== Sales (aman: jika tidak punya tabel/Model "sales", kasih default 0) ======
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        try {
            $salesToday = class_exists(Sale::class) ? (int)
            (Sale::whereDate('created_at', $today)->sum('total')) : 0;
            $salesMonth = class_exists(Sale::class) ? (int)
            (Sale::whereBetween('created_at', [$monthStart, now()])->sum('total')) : 0;
            $countSales = class_exists(Sale::class) ? (int) (Sale::count()) : 0;
        } catch (\Throwable $e) {
            $salesToday = $salesMonth = $countSales = 0;
        }
        // ====== Catalog ======
        $products = (int) Product::count();
        $variants = (int) ProductVariant::count();
        $failed = (int) SyncLog::where('status', 'failed')->count();
        // ====== Location ======
        $locationLabel = 'N/A';
        try {
            $locId = app(ShopifyInventoryService::class)->getDefaultLocationId();
            $locationLabel = (string) $locId;
        } catch (\Throwable $e) {
        }
        return [
            Stat::make('Penjualan Hari Ini', 'Rp ' . number_format(
                $salesToday,
                0,
                ',',
                '.'
            )),
            Stat::make('Penjualan Bulan Ini', 'Rp ' . number_format(
                $salesMonth,
                0,
                ',',
                '.'
            )),
            Stat::make('Jumlah Transaksi', (string) $countSales),
            Stat::make('Products', number_format($products)),
            Stat::make('Variants', number_format($variants)),
            Stat::make('Failed Sync', number_format($failed)),
            Stat::make('Default Location', $locationLabel)->description('Shopify'),
        ];
    }
}
