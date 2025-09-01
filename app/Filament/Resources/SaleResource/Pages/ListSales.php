<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Sale;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\HasTabs;
use Illuminate\Database\Eloquent\Builder;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        // ganti label tombol create → "New order"
        return [
            \Filament\Actions\CreateAction::make()->label('New order'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        // 3 kartu di header
        return [
            \App\Filament\Resources\SaleResource\Widgets\SalesStatsOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 3; // 3 kartu per baris (seperti mockup)
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Sale::query()->count()),

            'paid' => Tab::make('Paid')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'paid'))
                ->badge(Sale::query()->where('status', 'paid')->count()),

            'unpaid' => Tab::make('Unpaid')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'unpaid'))
                ->badge(Sale::query()->where('status', 'unpaid')->count()),

            'refunded' => Tab::make('Refunded')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'refunded'))
                ->badge(Sale::query()->where('status', 'refunded')->count()),

            'void' => Tab::make('Void')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'void'))
                ->badge(Sale::query()->where('status', 'void')->count()),
        ];
    }
}
