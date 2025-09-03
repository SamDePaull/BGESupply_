<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrdersPerMonthChart extends ChartWidget
{
    protected static ?string $heading = 'Order per bulan (tahun berjalan)';
    public function getColumnSpan(): int|string|array
    {
        return 6; // sesuaikan dengan layout grid dashboard-mu
    }

    protected function getData(): array
    {
        $year   = now()->year;
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $rows = Sale::query()
                ->selectRaw("EXTRACT(MONTH FROM created_at)::int AS m, COUNT(*) AS c")
                ->whereRaw("EXTRACT(YEAR FROM created_at) = ?", [$year])
                ->groupBy('m')
                ->orderBy('m')
                ->pluck('c', 'm')
                ->all();
        } elseif ($driver === 'sqlite') {
            $rows = Sale::query()
                ->selectRaw("CAST(STRFTIME('%m', created_at) AS INTEGER) AS m, COUNT(*) AS c")
                ->whereRaw("STRFTIME('%Y', created_at) = ?", [$year])
                ->groupBy('m')
                ->orderBy('m')
                ->pluck('c', 'm')
                ->all();
        } else { // mysql/mariadb
            $rows = Sale::query()
                ->selectRaw("MONTH(created_at) AS m, COUNT(*) AS c")
                ->whereYear('created_at', $year)
                ->groupBy('m')
                ->orderBy('m')
                ->pluck('c', 'm')
                ->all();
        }

        $labels = ['Januari', 'Februari','Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $data   = [];
        for ($i = 1; $i <= 12; $i++) $data[] = (int) ($rows[$i] ?? 0);

        return [
            'labels'   => $labels,
            'datasets' => [[
                'label'   => 'Orders',
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
