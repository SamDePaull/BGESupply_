<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfflineProductResource\Pages;
use App\Models\OfflineProduct;
use App\Models\Product as UnifiedProduct;
use App\Support\Filament\Perm;
use App\Services\OfflineProductService;

use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

// Forms
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;
use Illuminate\Validation\Rule;

// Table columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

// ✅ Table actions
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;

class OfflineProductResource extends Resource
{
    public static function shouldRegisterNavigation(): bool
    {
        return false; // tetap bisa diakses via URL, tapi tidak muncul di sidebar
    }

    protected static ?string $model = OfflineProduct::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Offline Products';
    protected static ?string $pluralLabel = 'Offline Products';

    public static function canViewAny(): bool
    {
        return Perm::can('offline_products.view');
    }

    public static function canCreate(): bool
    {
        return Perm::can('offline_products.create');
    }

    public static function canEdit($record): bool
    {
        return Perm::can('offline_products.update');
    }

    public static function canDelete($record): bool
    {
        return Perm::can('offline_products.delete');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Data Produk Offline')->schema([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),

                // SKU unik lintas offline_products & products (unified),
                // tetapi JANGAN error kalau SKU tidak berubah saat edit.
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->rules(function (?OfflineProduct $record) {
                        return [
                            // 1) Unik di offline_products, abaikan record ini
                            Rule::unique('offline_products', 'sku')->ignore($record?->id),

                            // 2) Custom rule untuk tabel products:
                            //    - HANYA cek jika SKU berubah dari nilai asli
                            //    - Abaikan unified milik offline ini
                            function (string $attribute, $value, \Closure $fail) use ($record) {
                                // Jika create (belum ada record) -> lakukan cek normal
                                $skuAsli = $record?->getOriginal('sku');

                                // Kalau sedang edit & SKU tidak berubah, skip pengecekan ke unified
                                if ($record && $skuAsli === $value) {
                                    return;
                                }

                                // Cari unified id yang terkait dengan offline ini (kalau ada)
                                $unifiedId = null;
                                if ($record) {
                                    $unifiedId = \App\Models\Product::where('origin', 'offline')
                                        ->where('origin_id', $record->id)
                                        ->value('id');
                                }

                                $exists = \App\Models\Product::where('sku', $value)
                                    ->when($unifiedId, fn($q) => $q->where('id', '!=', $unifiedId))
                                    ->exists();

                                if ($exists) {
                                    $fail('SKU sudah dipakai di tabel Products.');
                                }
                            },
                        ];
                    }),

                TextInput::make('price')->label('Harga')->numeric()->required(),
                TextInput::make('cost_price')->label('Harga Modal')->numeric()->nullable(),
                TextInput::make('stock')->label('Stok')->numeric()->required(),
                TextInput::make('image_url')->label('URL Gambar')->url()->nullable(),
                KeyValue::make('attributes')->label('Atribut (opsional)')->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('sku')->label('SKU')->searchable(),
                ImageColumn::make('image_url')->label('Gambar')->circular(),
                TextColumn::make('price')->label('Harga')->money('IDR')->sortable(),
                TextColumn::make('stock')->label('Stok')->sortable(),
                TextColumn::make('updated_at')->label('Update')->dateTime(),
            ])
            ->actions([
                EditAction::make(),

                Action::make('Sinkron ke Shopify')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(function ($record) {
                        $unified = \App\Models\Product::where([
                            'origin'    => 'offline',
                            'origin_id' => $record->id,
                        ])->first();

                        return ! $unified?->shopify_product_id;
                    })
                    ->action(function ($record) {
                        $ok = app(\App\Services\OfflineProductService::class)
                            ->syncExistingOfflineToShopify((int) $record->id);

                        \Filament\Notifications\Notification::make()
                            ->title($ok ? 'Produk tersinkron ke Shopify' : 'Gagal sinkron ke Shopify')
                            ->success($ok)->danger(! $ok)
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('Bulk Sinkron ke Shopify')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $svc = app(\App\Services\OfflineProductService::class);
                        $ok = 0;
                        $skip = 0;
                        $fail = 0;

                        foreach ($records as $rec) {
                            $unified = \App\Models\Product::where([
                                'origin'    => 'offline',
                                'origin_id' => $rec->id,
                            ])->first();

                            if ($unified?->shopify_product_id) {
                                $skip++;
                                continue;
                            }

                            $res = $svc->syncExistingOfflineToShopify((int) $rec->id);
                            $res ? $ok++ : $fail++;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title("Bulk sinkron selesai: {$ok} sukses, {$skip} dilewati, {$fail} gagal")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOfflineProducts::route('/'),
            'create' => Pages\CreateOfflineProduct::route('/create'),
            'edit'   => Pages\EditOfflineProduct::route('/{record}/edit'),
        ];
    }
}
