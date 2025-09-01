<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestOrders extends BaseWidget
{
    protected static ?string $heading = 'Latest Orders';

    /** table 1 kolom penuh (di bawah charts) */
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
                ->color(fn (string $s) => match ($s) {
                    'paid' => 'success',
                    'unpaid' => 'warning',
                    'refunded' => 'danger',
                    'void' => 'gray',
                    default => 'gray',
                })
                ->sortable(),

            Tables\Columns\TextColumn::make('total')
                ->label('Total price')
                ->money('IDR', true)
                ->sortable(),

            Tables\Columns\TextColumn::make('open')
                ->label(' ')
                ->formatStateUsing(fn () => 'Open')
                ->url(fn ($record) => route('filament.admin.resources.sales.view', $record)) // sesuaikan id panel/path
                ->color('primary')
                ->weight('medium'),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }
}
