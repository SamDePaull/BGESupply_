<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class CustomersTotalChart extends ChartWidget
{
    protected static ?string $heading = 'Total customers';
    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $data   = array_fill(0, 12, 0);

        // if (class_exists(\App\Models\Customer::class)) {
        //     $year = now()->year;
        //     $rows = \App\Models\Customer::query()
        //         ->selectRaw('MONTH(created_at) as m, COUNT(*) as c')
        //         ->whereYear('created_at', $year)
        //         ->groupBy('m')
        //         ->orderBy('m')
        //         ->pluck('c', 'm')
        //         ->all();

        //     // akumulasi agar “total customers” naik kumulatif
        //     $running = 0;
        //     for ($i = 1; $i <= 12; $i++) {
        //         $running += (int) ($rows[$i] ?? 0);
        //         $data[$i - 1] = $running;
        //     }
        // }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label' => 'Customers',
                    'data'  => $data,
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
