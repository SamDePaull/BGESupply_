<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components as Info;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Info\Section::make('Ringkasan')->columns(3)->schema([
                Info\TextEntry::make('number')->label('No. Nota')->copyable(),
                Info\TextEntry::make('customer_name')->label('Pelanggan')->default('-'),
                Info\TextEntry::make('payment_method')->label('Metode'),
                Info\TextEntry::make('status')->label('Status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success', 'unpaid' => 'warning', 'refunded' => 'danger', 'void' => 'gray', default => 'gray',
                    }),
                Info\TextEntry::make('subtotal')->label('Subtotal')->money('IDR'),
                Info\TextEntry::make('discount')->label('Diskon')->money('IDR'),
                Info\TextEntry::make('tax')->label('Pajak')->money('IDR'),
                Info\TextEntry::make('total')->label('Total')->money('IDR'),
                Info\TextEntry::make('paid_amount')->label('Dibayar')->money('IDR'),
                Info\TextEntry::make('change_amount')->label('Kembali')->money('IDR'),
                Info\TextEntry::make('created_at')->label('Tanggal')->dateTime('d M Y H:i'),
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
