<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Components\{Section, Grid, TextInput, Textarea, RichEditor, Fieldset, Repeater, Select, FileUpload, Toggle};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Enums\ActionSize;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 1;
    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Product Info')->schema([
                Grid::make(12)->schema([
                    TextInput::make('title')->required()->maxLength(255)->columnSpan(8)->live(onBlur: true)->afterStateUpdated(function ($state, Set $set, Get $get) {
                        if (!$get('handle')) $set('handle', (string)str($state)->slug('-'));
                    }),
                    TextInput::make('handle')->label('Handle')->maxLength(255)->columnSpan(4),
                ]),
                Grid::make(12)->schema([
                    TextInput::make('sku')->label('SKU (fallback)')->columnSpan(4),
                    TextInput::make('price')->numeric()->label('Price')->prefix('Rp')->columnSpan(4),
                    TextInput::make('compare_at_price')->numeric()->label('Compare at')->prefix('Rp')->columnSpan(4),
                ]),
                Grid::make(12)->schema([
                    TextInput::make('cost_price')->numeric()->label('Cost')->prefix('Rp')->columnSpan(4),
                    TextInput::make('inventory_quantity')->numeric()->default(0)->dehydrateStateUsing(fn($state) => (int)($state ?? 0))->label('Stock (fallback)')->columnSpan(4),
                    TextInput::make('vendor')->label('Vendor')->columnSpan(4),
                ]),
                Grid::make(12)->schema([
                    Select::make('category_id')
                        ->label('Category')
                        ->options(fn() => Category::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->nullable()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required(),
                            Forms\Components\TextInput::make('shopify_category')->label('Shopify Category (optional)'),
                        ])
                        ->createOptionAction(fn($action) => $action->modalHeading('Add Category'))->columnSpan(4),
                    TextInput::make('product_type')->label('Product Type')->columnSpan(4),
                    TextInput::make('tags')->label('Tags (comma separated)')->columnSpan(4),
                ]),
                Fieldset::make('Shipping & Tax')->schema([
                    Toggle::make('requires_shipping')->label('Requires shipping')->default(true),
                    Toggle::make('taxable')->label('Taxable')->default(true),
                    TextInput::make('weight')->numeric()->label('Weight'),
                    Select::make('weight_unit')->options(['g' => 'g', 'kg' => 'kg', 'oz' => 'oz', 'lb' => 'lb'])->default('g'),
                ])->columns(4),
                RichEditor::make('description')->columnSpanFull(),
            ])->collapsible(),

            Section::make('Publishing')->schema([
                Grid::make(12)->schema([
                    Select::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'])->required()->columnSpan(4),
                    Forms\Components\DateTimePicker::make('published_at')->native(false)->seconds(false)->columnSpan(4)->helperText('Biarkan kosong untuk auto saat Active'),
                    TextInput::make('seo_title')->maxLength(70)->columnSpan(6),
                    Textarea::make('seo_description')->maxLength(320)->columnSpan(6),
                ]),
            ])->collapsible(),

            Section::make('Media (Gallery)')->schema([
                Repeater::make('images')->relationship('images')->schema([
                    Grid::make(12)->schema([
                        FileUpload::make('file_path')->image()->directory('products')->disk('public')->imageEditor()->columnSpan(6)->helperText('Jalankan: php artisan storage:link'),
                        TextInput::make('alt')->columnSpan(6),
                        TextInput::make('position')->numeric()->columnSpan(3),
                        Toggle::make('is_primary')->label('Primary')->columnSpan(3),
                    ])
                ])->columns(1)->orderable('position')->createItemButtonLabel('Add image')->collapsible(),
            ])->collapsible(),

            Section::make('Variants (Options → Variants)')->schema([
                Repeater::make('options_schema')->label('Options')->schema([
                    TextInput::make('name')->required(),
                    Forms\Components\TagsInput::make('values')->required()->splitKeys([',', 'Enter'])
                ])->maxItems(3)->columns(1)->reorderable()->live(onBlur: true),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('syncVariants')->label('Sync variants from options')->icon('heroicon-o-arrow-path')->action(function (Get $get, Set $set) {
                        $options = $get('options_schema') ?: [];
                        $options = array_values(array_filter(array_map(function ($o) {
                            return ['name' => trim((string)($o['name'] ?? '')), 'values' => array_values(array_filter(array_map('trim', (array)($o['values'] ?? []))))];
                        }, $options), fn($o) => $o['name'] !== '' && !empty($o['values'])));
                        $options = array_slice($options, 0, 3);
                        $set('option1_name', $options[0]['name'] ?? null);
                        $set('option2_name', $options[1]['name'] ?? null);
                        $set('option3_name', $options[2]['name'] ?? null);
                        $lists = [$options[0]['values'] ?? [null], $options[1]['values'] ?? [null], $options[2]['values'] ?? [null]];
                        $combos = [[]];
                        foreach ($lists as $vals) {
                            $tmp = [];
                            foreach ($combos as $c) foreach ($vals as $v) {
                                $nc = $c;
                                $nc[] = $v;
                                $tmp[] = $nc;
                            }
                            $combos = $tmp;
                        }
                        $existing = $get('variants') ?: [];
                        $find = function ($o1, $o2, $o3) use ($existing) {
                            foreach ($existing as $ex) if (($ex['option1_value'] ?? null) === $o1 && ($ex['option2_value'] ?? null) === $o2 && ($ex['option3_value'] ?? null) === $o3) return $ex;
                            return null;
                        };
                        $result = [];
                        foreach ($combos as $c) {
                            [$o1, $o2, $o3] = [$c[0] ?? null, $c[1] ?? null, $c[2] ?? null];
                            $title = trim(implode(' / ', array_filter([$o1, $o2, $o3])));
                            $prev = $find($o1, $o2, $o3);
                            $result[] = ['title' => $title ?: 'Default', 'option1_value' => $o1, 'option2_value' => $o2, 'option3_value' => $o3, 'sku' => $prev['sku'] ?? null, 'barcode' => $prev['barcode'] ?? null, 'price' => $prev['price'] ?? null, 'compare_at_price' => $prev['compare_at_price'] ?? null, 'inventory_quantity' => $prev['inventory_quantity'] ?? 0, 'requires_shipping' => $prev['requires_shipping'] ?? true, 'taxable' => $prev['taxable'] ?? true, 'weight' => $prev['weight'] ?? null, 'weight_unit' => $prev['weight_unit'] ?? null, 'shopify_variant_id' => $prev['shopify_variant_id'] ?? null, 'shopify_inventory_item_id' => $prev['shopify_inventory_item_id'] ?? null, 'product_image_id' => $prev['product_image_id'] ?? null,];
                        }
                        $set('variants', $result);
                        Notification::make()->success()->title('Variants synced')->send();
                    })
                ])
            ])->collapsible(),

            Section::make('Variants list')->schema([
                Repeater::make('variants')->relationship('variants')->schema([
                    Grid::make(12)->schema([
                        TextInput::make('title')->label('Title')->columnSpan(4),
                        TextInput::make('option1_value')->label('Option 1')->columnSpan(4),
                        TextInput::make('option2_value')->label('Option 2')->columnSpan(4),
                        TextInput::make('option3_value')->label('Option 3')->columnSpan(4),
                        TextInput::make('sku')->label('SKU')->columnSpan(3),
                        TextInput::make('barcode')->label('Barcode')->columnSpan(3),
                        TextInput::make('price')->numeric()->dehydrateStateUsing(fn($state) => $state === '' ? null : $state)->label('Price')->columnSpan(3),
                        TextInput::make('compare_at_price')->numeric()->dehydrateStateUsing(fn($state) => $state === '' ? null : $state)->label('Compare at')->columnSpan(3),
                        TextInput::make('inventory_quantity')->numeric()->default(0)->dehydrateStateUsing(fn($state) => (int)($state ?? 0))->label('Stock')->columnSpan(3),
                        Toggle::make('requires_shipping')->label('Ship')->default(true)->columnSpan(2),
                        Toggle::make('taxable')->label('Tax')->default(true)->columnSpan(2),
                        TextInput::make('weight')->numeric()->label('Weight')->columnSpan(2),
                        Select::make('weight_unit')->options(['g' => 'g', 'kg' => 'kg', 'oz' => 'oz', 'lb' => 'lb'])->columnSpan(2),
                        // Select::make('product_image_id')->relationship('image', 'alt')->label('Image')->columnSpan(6),
                        FileUpload::make('tmp_image')->label('Upload image (optional)')->image()->directory('products')->visibility('public')->imageEditor()->helperText('Jika diunggah di sini, sistem akan membuat ProductImage & mengaitkannya ke varian setelah disimpan.')->columnSpan(6),
                        TextInput::make('shopify_variant_id')->disabled()->dehydrated(false)->columnSpan(6),
                        TextInput::make('shopify_inventory_item_id')->disabled()->dehydrated(false)->columnSpan(6),
                    ])
                ])->columns(1)->collapsible()->createItemButtonLabel('Add variant'),
            ])->collapsed(),

            Fieldset::make('Sync (Read-only)')->schema([
                TextInput::make('shopify_product_id')->disabled()->dehydrated(false),
                TextInput::make('sync_status')->disabled()->dehydrated(false),
                TextInput::make('last_synced_at')->disabled()->dehydrated(false),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(40),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->sortable()
                    ->formatStateUsing(fn($state) => is_null($state) ? '-' : 'Rp ' . number_format((float) $state, 0, ',', '.')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'active',
                        'gray'    => 'archived',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d M Y H:i')->label('Updated')->sortable(),
            ])

            /* =========================
         * ROW ACTIONS (ikon saja)
         * =======================*/
            ->actions([
                // Toggle sync
                Tables\Actions\Action::make('toggleSync')
                    ->label(null) // tidak menampilkan teks
                    ->icon(fn($record) => $record->sync_enabled ? 'heroicon-o-link-slash' : 'heroicon-o-link')
                    ->color(fn($record) => $record->sync_enabled ? 'warning' : 'success')
                    ->iconButton()                  // ⬅️ render sebagai icon button
                    ->size(ActionSize::Small)       // ⬅️ ukuran kecil
                    ->tooltip(fn($record) => $record->sync_enabled ? 'Putus Sinkronisasi' : 'Aktifkan Sinkronisasi')
                    ->requiresConfirmation()
                    ->modalHeading(fn($r) => $r->sync_enabled ? 'Putus sinkronisasi produk ini?' : 'Aktifkan sinkronisasi produk ini?')
                    ->modalDescription(fn($r) => $r->sync_enabled
                        ? 'Produk ini tidak akan lagi diperbarui otomatis ke Shopify sampai Anda mengaktifkannya kembali.'
                        : 'Produk ini akan kembali mengikuti pembaruan otomatis ke Shopify.')
                    ->modalSubmitActionLabel('Lanjutkan')
                    ->action(function ($record) {
                        $record->sync_enabled = ! $record->sync_enabled;
                        $record->saveQuietly();
                        \Filament\Notifications\Notification::make()->title('Status sinkronisasi diperbarui')->success()->send();
                    }),

                // Push
                Tables\Actions\Action::make('pushToShopify')
                    ->label(null)
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('primary')
                    ->iconButton()
                    ->size(ActionSize::Small)
                    ->tooltip('Push ke Shopify')
                    ->visible(fn($record) => $record->sync_enabled)
                    ->requiresConfirmation()
                    ->modalHeading('Kirim pembaruan ke Shopify?')
                    ->modalDescription('Tindakan ini akan memperbarui produk di Shopify sesuai data saat ini.')
                    ->modalSubmitActionLabel('Push sekarang')
                    ->action(fn($record) => app(\App\Services\ShopifyPushService::class)->updateOnShopify($record)),

                // Delete (Shopify + Offline)
                Tables\Actions\Action::make('deleteBoth')
                    ->label(null)
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->iconButton()
                    ->size(ActionSize::Small)
                    ->tooltip('Delete (Shopify + Offline)')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus produk ini?')
                    ->modalDescription('Produk akan dihapus dari Shopify (jika tersinkron) dan dari database. Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Ya, hapus')
                    ->action(function (\App\Models\Product $record) {
                        try {
                            if ($record->shopify_product_id) {
                                app(\App\Services\ShopifyPushService::class)->deleteOnShopify($record);
                            }
                        } catch (\Throwable $e) {
                        }
                        $record->delete();
                        \Filament\Notifications\Notification::make()->title('Produk dihapus')->body('Dihapus dari Shopify (jika ada) dan database.')->success()->send();
                    }),

                // Edit
                Tables\Actions\EditAction::make()
                    ->label(null)
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->size(ActionSize::Small)
                    ->tooltip('Edit'),
            ])


            /* =========================
         * BULK ACTIONS (lebih cantik)
         * =======================*/
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkToggleSync')
                        ->label('Toggle Sync')
                        ->icon('heroicon-o-link')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Toggle sinkronisasi (bulk)')
                        ->modalDescription('Semua produk terpilih akan dibalik status sinkronisasinya.')
                        ->modalSubmitActionLabel('Ya, lanjutkan')
                        ->action(function ($records) {
                            foreach ($records as $rec) {
                                $rec->sync_enabled = ! $rec->sync_enabled;
                                $rec->saveQuietly();
                            }
                            \Filament\Notifications\Notification::make()->title('Sinkronisasi diperbarui')->body('Status sync untuk produk terpilih telah diperbarui.')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('bulkPushShopify')
                        ->label('Push ke Shopify')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Push ke Shopify (bulk)?')
                        ->modalDescription('Produk terpilih yang statusnya aktif sinkron akan dipush ke Shopify.')
                        ->modalSubmitActionLabel('Push sekarang')
                        ->action(function ($records) {
                            $svc = app(\App\Services\ShopifyPushService::class);
                            foreach ($records as $rec) {
                                if ($rec->sync_enabled) {
                                    $svc->updateOnShopify($rec);
                                }
                            }
                            \Filament\Notifications\Notification::make()->title('Produk dipush')->body('Produk terpilih telah dipush ke Shopify (jika sinkron).')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('bulkDeleteBoth')
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Hapus produk (bulk)?')
                        ->modalDescription('Semua produk terpilih akan dihapus dari Shopify (jika ada) dan dari database. Aksi ini tidak dapat dibatalkan.')
                        ->modalSubmitActionLabel('Ya, hapus')
                        ->action(function ($records) {
                            $svc = app(\App\Services\ShopifyPushService::class);
                            foreach ($records as $rec) {
                                try {
                                    if ($rec->shopify_product_id) {
                                        $svc->deleteOnShopify($rec);
                                    }
                                } catch (\Throwable $e) {
                                }
                                $rec->delete();
                            }
                            \Filament\Notifications\Notification::make()->title('Produk terpilih dihapus')->body('Dihapus dari Shopify (jika ada) dan database.')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ])
                    ->label('Aksi')
                    ->color('gray')              // trigger dropdown tanpa teks
                    ->icon('heroicon-o-sparkles') // ikon kecil untuk bulk
                    ->iconButton()                // ⬅️ render sebagai icon button
                    // ->size(ActionSize::Small)     // ⬅️ kecil
                    // ->extraAttributes(['class' => 'p-1']) // ⬅️ makin hemat ruang
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProducts::route('/'), 'create' => Pages\CreateProduct::route('/create'), 'edit' => Pages\EditProduct::route('/{record}/edit'),];
    }
}
