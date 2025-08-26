<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function getNavigationGroup(): ?string
    {
        return 'Dashboard';
    }
    public function getWidgets(): array
    {
        return [
            // Widget lama kamu (bila ingin dipertahankan) \App\Filament\Widgets\SalesOverview::class, // boleh hapus baris ini kalau pakai OpsOverview saja
            // Widget baru operasional
            \App\Filament\Widgets\OpsOverview::class,
            // Widget existing di project-mu
            // \App\Filament\Widgets\StockByOrigin::class,
            \App\Filament\Widgets\TopProducts::class,
            // Logs sinkronisasi
            \App\Filament\Widgets\RecentSyncLogs::class,
        ];
    }
    public function getColumns(): int|string|array
    {
        return 12; // grid 12 kolom
    }
}
