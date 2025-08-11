<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\ShopifyVariantsRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\ShopifyImagesRelationManager;
use App\Models\Product;
use App\Support\Filament\Perm;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\{TextColumn, ImageColumn, BadgeColumn};
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\{EditAction, Action, BulkAction};
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;

use App\Services\ShopifyPushService;
use App\Services\ShopifyService;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Products (Unified)';

    public static function canViewAny(): bool
    {
        return Perm::can('products.view');
    }

    public static function canCreate(): bool
    {
        return Perm::can('products.create');
    }

    public static function canEdit($record): bool
    {
        return Perm::can('products.update');
    }

    public static function canDelete($record): bool
    {
        return Perm::can('products.delete');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->disabled(fn (?Product $record) => $record?->is_from_shopify)
                ->required(),
            TextInput::make('sku')
                ->disabled(fn (?Product $record) => $record?->is_from_shopify)
                ->required(),
            TextInput::make('price')->numeric()->required(),
            TextInput::make('cost_price')->numeric()->nullable(),
            TextInput::make('stock')->numeric()->required(),
            TextInput::make('image_url')->url()->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('sku')->label('SKU')->toggleable(),
                ImageColumn::make('image_url')->label('Gambar')->circular(),
                TextColumn::make('price')->money('IDR')->sortable(),
                TextColumn::make('stock')->sortable(),
                BadgeColumn::make('asal')
                    ->label('Asal')
                    ->getStateUsing(fn($record) => $record->is_from_shopify ? 'Shopify' : 'Offline')
                    ->colors([
                        'success' => fn($state) => $state === 'Shopify',
                        'warning' => fn($state) => $state === 'Offline',
                    ]),
                BadgeColumn::make('sync_status')->colors([
                    'success' => 'synced',
                    'warning' => 'pending',
                    'danger'  => 'failed',
                ]),
                BadgeColumn::make('stock')
                    ->label('Stok')
                    ->formatStateUsing(fn ($state) => (string) $state)
                    ->colors([
                        'danger' => fn ($record) => $record->is_low_stock,
                        'success' => fn ($record) => ! $record->is_low_stock,
                    ])
                    ->sortable(),
                TextColumn::make('updated_at')->label('Update Terakhir')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('origin')
                    ->label('Asal Produk')
                    ->options(['shopify' => 'Shopify', 'offline' => 'Offline']),
                SelectFilter::make('sync_status')->options([
                    'synced'  => 'Synced',
                    'pending' => 'Pending',
                    'failed'  => 'Failed',
                ]),
                TernaryFilter::make('low_stock')
                    ->label('Low Stock?')
                    ->placeholder('Semua')
                    ->trueLabel('Ya (≤ threshold)')
                    ->falseLabel('Bukan')
                    ->queries(
                        true: fn ($q) => $q->where('stock', '<=', (int) config('inventory.low_stock_threshold', 5)),
                        false: fn ($q) => $q->where('stock', '>', (int) config('inventory.low_stock_threshold', 5)),
                        blank: fn ($q) => $q
                    ),
            ])
            ->headerActions([
                Action::make('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(route('export.products.csv'))
                    ->openUrlInNewTab(),

                Action::make('Pull & Ingest dari Shopify')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn() => \App\Support\Filament\Perm::can('shopify.refresh')) // opsional
                    ->action(function () {
                        $count = app(ShopifyService::class)->pullAndIngest();
                        Notification::make()
                            ->title("Pull & Ingest selesai: {$count} produk diproses")
                            ->success()
                            ->send();
                    }),

            ])
            ->actions([
                EditAction::make(),

                Action::make('Push to Shopify')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->visible(fn ($record) => $record->origin === 'offline' && !$record->shopify_product_id)
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(function ($record) {
                        $ok = app(ShopifyPushService::class)->pushUnifiedToShopify($record);
                        Notification::make()
                            ->title($ok ? 'Berhasil push ke Shopify' : 'Gagal push ke Shopify')
                            ->success($ok)->danger(!$ok)->send();
                    }),

                Action::make('Refresh dari Shopify')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record) => filled($record->shopify_product_id))
                    ->requiresConfirmation()
                    ->color('gray')
                    ->action(function ($record) {
                        $ok = app(ShopifyService::class)->pullSingleProductAndIngest((int)$record->shopify_product_id);
                        Notification::make()
                            ->title($ok ? 'Produk diperbarui dari Shopify' : 'Gagal mengambil data')
                            ->success($ok)->danger(!$ok)->send();
                    }),

                Action::make('Delete on Shopify')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn($record) => $record->shopify_product_id) // tampil jika terhubung ke Shopify
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $ok = app(ShopifyPushService::class)->deleteOnShopify((int)$record->shopify_product_id);
                        Notification::make()
                            ->title($ok ? 'Produk dihapus dari Shopify' : 'Gagal menghapus di Shopify')
                            ->success($ok)->danger(!$ok)->send();
                        if ($ok) $record->refresh();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('Bulk Push to Shopify')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(function ($records) {
                        $svc = app(ShopifyPushService::class);
                        $okCount = 0; $fail = 0;

                        foreach ($records as $record) {
                            if ($record->origin === 'offline' && !$record->shopify_product_id) {
                                $ok = $svc->pushUnifiedToShopify($record);
                                $ok ? $okCount++ : $fail++;
                            }
                        }

                        Notification::make()
                            ->title("Bulk Push selesai: {$okCount} sukses, {$fail} gagal")
                            ->success()
                            ->send();
                    }),
                DeleteBulkAction::make(),

                BulkAction::make('Bulk Refresh from Shopify')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->color('gray')
                    ->action(function ($records) {
                        $svc = app(ShopifyService::class);
                        $ok = 0; $skip = 0; $fail = 0;

                        foreach ($records as $record) {
                            if ($record->origin !== 'shopify' || !$record->shopify_product_id) {
                                $skip++;
                                continue;
                            }
                            $res = $svc->pullSingleProductAndIngest((int)$record->shopify_product_id);
                            $res ? $ok++ : $fail++;
                        }

                        Notification::make()
                            ->title("Bulk Refresh: {$ok} sukses, {$skip} dilewati, {$fail} gagal")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ShopifyVariantsRelationManager::class,
            ShopifyImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'edit'  => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
