<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RevenuePerMonthChart extends ChartWidget
{
    protected static ?string $heading = 'Revenue per Bulan (tahun berjalan)';

    // Biar bisa sejajar (2 kolom: 6 + 6 dari grid 12)
    public function getColumnSpan(): int|string|array
    {
        return 6; // sesuaikan dengan layout grid dashboard-mu
    }

    protected function getType(): string
    {
        // samakan tipe dengan grafik "Order per bulan" kamu (bar/line)
        return 'line';
    }

    protected function getData(): array
    {
        $year   = now()->year;
        $driver = DB::connection()->getDriverName();

        // label bulan (ID)
        $labels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $data   = array_fill(0, 12, 0);

        // Query aman lintas DB (Postgres/MySQL/SQLite)
        if ($driver === 'pgsql') {
            $rows = DB::table('sales')
                ->selectRaw('EXTRACT(MONTH FROM "created_at")::int as m, SUM(total)::numeric as s')
                ->whereRaw('EXTRACT(YEAR FROM "created_at") = ?', [$year])
                ->where('status', 'paid')              // revenue dari transaksi lunas
                ->groupBy('m')
                ->orderBy('m')
                ->get();
        } elseif ($driver === 'sqlite') {
            $rows = DB::table('sales')
                ->selectRaw('CAST(strftime("%m", "created_at") as integer) as m, SUM(total) as s')
                ->whereRaw('strftime("%Y", "created_at") = ?', [$year])
                ->where('status', 'paid')
                ->groupBy('m')
                ->orderBy('m')
                ->get();
        } else { // mysql/mariadb
            $rows = DB::table('sales')
                ->selectRaw('MONTH(created_at) as m, SUM(total) as s')
                ->whereYear('created_at', $year)
                ->where('status', 'paid')
                ->groupBy('m')
                ->orderBy('m')
                ->get();
        }

        foreach ($rows as $r) {
            $idx = max(1, (int) $r->m) - 1;   // 0..11
            $data[$idx] = (float) $r->s;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Revenue (Rp)',
                    'data' => $data,
                    'tension' => 0.35,     // sedikit halus
                ],
            ],
        ];
    }
}
