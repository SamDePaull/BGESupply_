<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CustomersTotalChart extends ChartWidget
{
    protected static ?string $heading = 'Total customers';
    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $data   = array_fill(0, 12, 0);

        // if (class_exists(\App\Models\Customer::class)) {
        //     $year   = now()->year;
        //     $driver = DB::connection()->getDriverName();

        //     if ($driver === 'pgsql') {
        //         $rows = \App\Models\Customer::query()
        //             ->selectRaw("EXTRACT(MONTH FROM created_at)::int AS m, COUNT(*) AS c")
        //             ->whereRaw("EXTRACT(YEAR FROM created_at) = ?", [$year])
        //             ->groupBy('m')
        //             ->orderBy('m')
        //             ->pluck('c', 'm')
        //             ->all();
        //     } elseif ($driver === 'sqlite') {
        //         $rows = \App\Models\Customer::query()
        //             ->selectRaw("CAST(STRFTIME('%m', created_at) AS INTEGER) AS m, COUNT(*) AS c")
        //             ->whereRaw("STRFTIME('%Y', created_at) = ?", [$year])
        //             ->groupBy('m')
        //             ->orderBy('m')
        //             ->pluck('c', 'm')
        //             ->all();
        //     } else {
        //         $rows = \App\Models\Customer::query()
        //             ->selectRaw("MONTH(created_at) AS m, COUNT(*) AS c")
        //             ->whereYear('created_at', $year)
        //             ->groupBy('m')
        //             ->orderBy('m')
        //             ->pluck('c', 'm')
        //             ->all();
        //     }

        //     $running = 0;
        //     for ($i = 1; $i <= 12; $i++) {
        //         $running += (int) ($rows[$i] ?? 0);
        //         $data[$i - 1] = $running;
        //     }
        // }

        return [
            'labels'   => $labels,
            'datasets' => [[
                'label'   => 'Customers',
                'data'    => $data,
                'tension' => 0.3,
                'fill'    => false,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
