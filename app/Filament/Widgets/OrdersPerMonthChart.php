<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrdersPerMonthChart extends ChartWidget
{
    protected static ?string $heading = 'Orders per month';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $year = now()->year;

        $rows = Sale::query()
            ->selectRaw('MONTH(created_at) as m, COUNT(*) as c')
            ->whereYear('created_at', $year)
            ->groupBy('m')
            ->orderBy('m')
            ->pluck('c', 'm')
            ->all();

        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $data   = [];
        for ($i = 1; $i <= 12; $i++) {
            $data[] = (int) ($rows[$i] ?? 0);
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data'  => $data,
                    // jangan set warna manual (biar pakai default theme)
                    'tension' => 0.3,
                    'fill'    => false,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
