<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';

    /** Widget di baris header (stat cards + welcome) */
    public function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\DashboardStatsOverview::class,
        ];
    }

    /** Kolom untuk header widgets (3 kartu + 1 welcome) */
    public function getHeaderWidgetsColumns(): int|string|array
    {
        return [
            'sm' => 1,
            'lg' => 4,
        ];
    }

    /** Widget isi dashboard (chart + latest orders) */
    public function getWidgets(): array
    {
        return [
            // \App\Filament\Widgets\OpsOverview::class,
            // \App\Filament\Widgets\StockByOrigin::class,
            // \App\Filament\Widgets\RecentSyncLogs::class,
            \App\Filament\Widgets\OrdersPerMonthChart::class,
            \App\Filament\Widgets\TopProducts::class,
            \App\Filament\Widgets\CustomersTotalChart::class,
            \App\Filament\Widgets\LatestOrders::class,
        ];
    }

    /** Grid body: 2 kolom untuk charts, table di bawah penuh */
    public function getColumns(): int|string|array
    {
        return [
            'sm' => 1,
            'lg' => 2,
        ];
    }
}
