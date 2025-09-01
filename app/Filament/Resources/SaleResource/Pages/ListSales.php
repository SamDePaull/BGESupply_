<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\SaleResource\Widgets\SaleStatusTabs;
use App\Filament\Resources\SaleResource\Widgets\SalesStatsOverview;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    /** Tombol header – sisakan Create saja, tabs dipindah ke widget */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New order'),
        ];
    }

    /** Filter query tabel berdasar ?status=... (pengganti HasTabs Pro) */
    protected function getTableQuery(): Builder
    {
        $query  = parent::getTableQuery();
        $status = request()->query('status');

        if (in_array($status, ['paid', 'unpaid', 'refunded', 'void'], true)) {
            $query->where('status', $status);
        }
        return $query;
    }

    public function getHeaderWidgets(): array
    {
        return [
            SalesStatsOverview::class, // 3 kolom
            SaleStatusTabs::class,     // tabs (full)
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        // Tabs akan span 'full', baris berikutnya 3 kolom untuk stats
        return 1;
    }
}
