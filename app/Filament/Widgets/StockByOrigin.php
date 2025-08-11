<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\ChartWidget;

class StockByOrigin extends ChartWidget
{
    protected static ?string $heading = 'Stok Berdasarkan Asal';
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $byOrigin = Product::query()
            ->selectRaw("origin, SUM(stock) as qty")
            ->groupBy('origin')
            ->pluck('qty', 'origin')
            ->toArray();

        $labels = array_keys($byOrigin);
        $values = array_values($byOrigin);

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Stok', 'data' => $values],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
