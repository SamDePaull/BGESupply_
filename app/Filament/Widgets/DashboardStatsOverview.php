<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class DashboardStatsOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $revenue = (float) (Sale::query()->sum('total') ?? 0);
        $newOrders30d = (int) Sale::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            Card::make('Revenue', 'Rp ' . number_format($revenue, 0, ',', '.'))
                ->description('Total pendapatan')
                ->icon('heroicon-o-currency-dollar'),


            Card::make('New orders', number_format($newOrders30d))
                ->description('30 hari terakhir')
                ->icon('heroicon-o-queue-list'),
        ];
    }

    protected function getColumns(): int
    {
            return 2;
    }
}
