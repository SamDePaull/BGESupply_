<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Kasir / Penjualan';
    protected static ?int $navigationSort = 10;
    protected static ?string $modelLabel = 'Penjualan';
    protected static ?string $pluralModelLabel = 'Penjualan';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('No. Nota')->searchable(),
                Tables\Columns\TextColumn::make('customer_name')->label('Pelanggan')->toggleable(),
                Tables\Columns\TextColumn::make('total')->money('idr', true)->label('Total'),
                Tables\Columns\TextColumn::make('payment_method')->label('Metode'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Dibuat'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tidak menyediakan Edit untuk menjaga integritas histori
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'view' => Pages\ViewSale::route('/{record}'),
        ];
    }
}
