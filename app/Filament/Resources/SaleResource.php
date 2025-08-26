<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use App\Support\Filament\Perm;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

// Forms
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;

// Tables
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ViewAction;

class SaleResource extends Resource
{
    public static function shouldRegisterNavigation(): bool
    {
        return false; // tetap bisa diakses via URL, tapi tidak muncul di sidebar
    }


    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'POS Sales';

    public static function canViewAny(): bool
    {
        return Perm::can('sales.view');
    }

    public static function canCreate(): bool
    {
        return Perm::can('sales.create');
    }

    public static function canEdit($record): bool
    {
        return Perm::can('sales.update');
    }

    public static function canDelete($record): bool
    {
        return Perm::can('sales.delete');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Transaksi')->schema([
                TextInput::make('invoice_number')->required()->unique(Sale::class, 'invoice_number', ignoreRecord: true),
                TextInput::make('payment_method')->default('cash'),
                TextInput::make('total')->numeric()->required()
                    ->helperText('Total akan dihitung otomatis saat simpan berdasarkan item'),
            ])->columns(2),

            Section::make('Items')->schema([
                Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Select::make('product_id')
                            ->label('Produk')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('quantity')->numeric()->default(1)->required(),
                        TextInput::make('price')->numeric()->required()
                            ->helperText('Harga per item; default isi dari harga produk'),
                        TextInput::make('subtotal')->numeric()->disabled()
                            ->dehydrated(false)
                            ->helperText('Akan dihitung otomatis saat simpan'),
                    ])
                    ->columns(4)
                    ->createItemButtonLabel('Tambah Item'),
            ])->collapsed(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')->searchable(),
                TextColumn::make('total')->money('IDR')->sortable(),
                TextColumn::make('payment_method'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
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
