<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\DashboardStatsOverview::class,
            // \App\Filament\Widgets\WelcomeCard::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return [
            'sm' => 1,
            'lg' => 4,
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\RevenuePerMonthChart::class,
            \App\Filament\Widgets\OrdersPerMonthChart::class,
            \App\Filament\Widgets\TopProducts::class,
            // \App\Filament\Widgets\CustomersTotalChart::class,
            \App\Filament\Widgets\LatestOrders::class,
        ];
    }

    public function getColumns(): int|array
    {
        // pastikan 12 kolom supaya 6 + 6 jadi sejajar
        return 12;
    }
}
