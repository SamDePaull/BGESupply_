<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components as Info;
use Filament\Tables;
use Filament\Tables\Table;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Info\Section::make('Info Penjualan')
                    ->columns(3)
                    ->schema([
                        Info\TextEntry::make('number')->label('No. Nota'),
                        Info\TextEntry::make('customer_name')->label('Pelanggan'),
                        Info\TextEntry::make('cashier.name')->label('Kasir')->default('—'),
                        Info\TextEntry::make('subtotal')->label('Subtotal')->money('idr', true),
                        Info\TextEntry::make('discount')->label('Diskon')->money('idr', true),
                        Info\TextEntry::make('tax')->label('PPN')->money('idr', true),
                        Info\TextEntry::make('total')->label('Total')->money('idr', true),
                        Info\TextEntry::make('paid_amount')->label('Dibayar')->money('idr', true),
                        Info\TextEntry::make('change_amount')->label('Kembalian')->money('idr', true),
                        Info\TextEntry::make('payment_method')->label('Metode'),
                        Info\TextEntry::make('status')->label('Status'),
                        Info\TextEntry::make('created_at')->label('Tanggal')->dateTime(),
                    ]),
            ]);
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Resources\SaleResource\Widgets\SaleItemsTable::class,
        ];
    }
}
