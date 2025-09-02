<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use Filament\Forms;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Route;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon   = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel  = 'Transaksi';
    protected static ?int    $navigationSort   = 3;
    protected static ?string $modelLabel       = 'Penjualan';
    protected static ?string $pluralModelLabel = 'Penjualan';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Number')->searchable()->sortable()->copyable()
                    ->extraAttributes(['class' => 'font-medium']),
                TextColumn::make('customer_name')->label('Customer')->searchable()->toggleable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn(string $state) => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'warning',
                        'refunded' => 'danger',
                        'void' => 'gray',
                        default => 'gray',
                    })->sortable(),
                TextColumn::make('payment_method')->label('Method')->badge()
                    ->formatStateUsing(fn($state) => match ((string) $state) {
                        'cash' => 'Cash',
                        'qris' => 'QRIS',
                        'transfer' => 'Transfer',
                        'card' => 'Card',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn($state) => match ((string) $state) {
                        'cash' => 'gray',
                        'qris' => 'info',
                        'transfer' => 'warning',
                        'card' => 'primary',
                        default => 'gray',
                    })->toggleable(),
                TextColumn::make('total')->label('Total price')->money('IDR', true)->sortable()
                    ->summarize([Sum::make()->label('Sum')]),
                TextColumn::make('created_at')->label('Order Date')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                Filter::make('date')->form([
                    Forms\Components\DatePicker::make('from')->label('From'),
                    Forms\Components\DatePicker::make('until')->label('Until'),
                ])->query(function (Builder $q, array $data) {
                    return $q
                        ->when($data['from'] ?? null, fn($qq, $d) => $qq->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn($qq, $d) => $qq->whereDate('created_at', '<=', $d));
                })->indicateUsing(function (array $data) {
                    $i = [];
                    if ($data['from'] ?? null)  $i[] = 'From ' . \Illuminate\Support\Carbon::parse($data['from'])->format('d M Y');
                    if ($data['until'] ?? null) $i[] = 'Until ' . \Illuminate\Support\Carbon::parse($data['until'])->format('d M Y');
                    return $i;
                }),
                // SelectFilter::make('status')->label('Status')->options([
                //     'paid' => 'Paid',
                //     'unpaid' => 'Unpaid',
                //     'refunded' => 'Refunded',
                //     'void' => 'Void',
                // ])->native(false),
                SelectFilter::make('payment_method')->label('Method')->options([
                    'cash' => 'Cash',
                    'qris' => 'QRIS',
                    'transfer' => 'Transfer',
                    'card' => 'Card',
                ])->native(false),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()->label('Detail')->slideOver(),
                    Action::make('receipt')
                        ->label('Receipt')
                        ->icon('heroicon-o-printer')
                        ->visible(fn() => Route::has('receipt.pdf')) // aman kalau rute dihapus
                        ->url(fn($record) => route('receipt.pdf', ['sale' => $record->getKey()]), shouldOpenInNewTab: true),
                    // WA tersembunyi karena 'customer_phone' tidak ada di model
                    Action::make('whatsapp')->label('Send WA')->icon('heroicon-o-paper-airplane')
                        ->hidden(fn($record) => blank($record->customer_phone ?? null))
                        ->url(
                            fn($record) => 'https://wa.me/' . $record->customer_phone . '?text=' .
                                urlencode("Halo, ringkasan transaksi #{$record->number} total Rp " . number_format($record->total, 0, ',', '.')),
                            shouldOpenInNewTab: true
                        ),
                ])->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->striped()
            ->emptyStateHeading('Belum ada penjualan')
            ->emptyStateDescription('Transaksi yang tersimpan akan muncul di sini.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'view'   => Pages\ViewSale::route('/{record}'),
        ];
    }
}
