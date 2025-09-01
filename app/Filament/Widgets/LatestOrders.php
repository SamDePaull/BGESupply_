<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SaleResource;
use App\Models\Sale;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestOrders extends BaseWidget
{
    protected static ?string $heading = 'Latest Orders';
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Sale::query()->latest('created_at');
    }

    public function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return 5;
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')
                ->label('Order Date')
                ->date('M d, Y')
                ->sortable(),

            Tables\Columns\TextColumn::make('number')
                ->label('Number')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('customer_name')
                ->label('Customer')
                ->searchable(),

            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    'paid' => 'success', 'unpaid' => 'warning', 'refunded' => 'danger', 'void' => 'gray', default => 'gray',
                })
                ->sortable(),

            Tables\Columns\TextColumn::make('total')
                ->label('Total price')
                ->money('IDR', true)
                ->sortable(),

            Tables\Columns\TextColumn::make('open')
                ->label(' ')
                ->formatStateUsing(fn () => 'Open')
                ->url(fn ($record) => SaleResource::getUrl('view', ['record' => $record]))
                ->color('primary')
                ->weight('medium'),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }
}
