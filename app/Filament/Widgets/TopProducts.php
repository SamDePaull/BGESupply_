<?php

namespace App\Filament\Widgets;

use App\Models\SaleItem;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class TopProducts extends BaseWidget
{
    protected int|string|array $columnSpan = "full";
    protected static ?string $heading = 'Produk Terlaris (Top 10)';

    protected function getTableQuery(): Builder
    {
        return SaleItem::query()
            ->selectRaw('product_id, SUM(quantity) as qty, SUM(subtotal) as total')
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->with('product')
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('product.name')->label('Produk')->searchable(),
            Tables\Columns\TextColumn::make('qty')->label('Qty')->sortable(),
            Tables\Columns\TextColumn::make('total')->label('Total')->money('IDR')->sortable(),
        ];
    }
}
