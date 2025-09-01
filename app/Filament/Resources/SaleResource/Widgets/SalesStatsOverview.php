<?php

namespace App\Filament\Resources\SaleResource\Widgets;

use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class SalesStatsOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $orders = Sale::query()->count();
        $open   = Sale::query()->where('status', 'unpaid')->count(); // "open" = unpaid
        $avg    = (float) (Sale::query()->avg('total') ?? 0);

        return [
            Card::make('Orders', number_format($orders))
                ->icon('heroicon-o-queue-list'),

            Card::make('Open orders', number_format($open))
                ->icon('heroicon-o-clock'),

            Card::make('Average price', 'Rp ' . number_format($avg, 0, ',', '.'))
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
