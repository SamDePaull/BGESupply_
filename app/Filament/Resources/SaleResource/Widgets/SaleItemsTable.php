<?php

namespace App\Filament\Resources\SaleResource\Widgets;

use App\Filament\Resources\SaleResource;
use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SaleItemsTable extends BaseWidget
{
    protected static ?string $heading = 'Items';
    protected static ?string $resource = SaleResource::class;

    /** Akan diisi otomatis saat widget dirender pada halaman ViewRecord */
    public ?Sale $record = null;

    public function table(Table $table): Table
    {
        $sale = method_exists($this, 'getOwnerRecord')
            ? $this->getOwnerRecord()
            : $this->record;

        return $table
            ->query(fn () => $sale
                ? $sale->items()->orderBy('id')
                : SaleItem::query()->whereRaw('1=0')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Item')
                    ->wrap()
                    ->limit(60),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state) => $state === null || $state === ''
                        ? '-'
                        : 'Rp ' . number_format((float)$state, 0, ',', '.')
                    ),

                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty'),

                Tables\Columns\TextColumn::make('line_total')
                    ->label('Subtotal')
                    ->formatStateUsing(fn ($state) => $state === null || $state === ''
                        ? '-'
                        : 'Rp ' . number_format((float)$state, 0, ',', '.')
                    ),
            ])
            ->paginated(false)
            ->striped();
    }
}
