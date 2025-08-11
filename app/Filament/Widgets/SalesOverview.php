<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SalesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $salesToday = Sale::whereDate('created_at', $today)->sum('total');
        $salesMonth = Sale::whereBetween('created_at', [$monthStart, now()])->sum('total');
        $countSales = Sale::count();

        return [
            Stat::make('Penjualan Hari Ini', 'Rp ' . number_format($salesToday, 0, ',', '.')),
            Stat::make('Penjualan Bulan Ini', 'Rp ' . number_format($salesMonth, 0, ',', '.')),
            Stat::make('Jumlah Transaksi', (string) $countSales),
        ];
    }
}
