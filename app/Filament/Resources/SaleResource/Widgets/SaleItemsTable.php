<?php

namespace App\Filament\Resources\SaleResource\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SaleItemsTable extends BaseWidget
{
    protected static ?string $heading = 'Item Penjualan';

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        /** @var \App\Models\Sale $sale */
        $sale = $this->getRecord();

        return $sale?->items()?->getQuery();
    }
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU'),
                Tables\Columns\TextColumn::make('name')->label('Nama'),
                Tables\Columns\TextColumn::make('qty')->label('Qty'),
                Tables\Columns\TextColumn::make('price')->label('Harga')->money('idr', true),
                Tables\Columns\TextColumn::make('line_total')->label('Subtotal')->money('idr', true),
            ])
            ->paginated(false);
    }
}
