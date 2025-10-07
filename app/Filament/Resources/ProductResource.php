<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Components\{
    Builder,
    Section,
    Grid,
    Group,
    TextInput,
    Textarea,
    RichEditor,
    Fieldset,
    Repeater,
    Select,
    FileUpload,
    Toggle,
    DateTimePicker
};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Notifications\Notification;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Enums\ActionSize;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Katalog Produk';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(12)->schema([

                /* =========================
                 *  KIRI (konten utama) 8/12
                 * ========================= */
                Group::make()->schema([

                    // ===== Product Info (Name/Handle/Description) =====
                    Section::make('')
                        ->schema([
                            Grid::make(12)->schema([
                                TextInput::make('title')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(8)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (!$get('handle')) {
                                            $set('handle', (string) str($state)->slug('-'));
                                        }
                                    }),

                                TextInput::make('handle')
                                    ->label('Handle')
                                    ->maxLength(255)
                                    ->columnSpan(4),
                            ]),

                            RichEditor::make('description')
                                ->label('Description')
                                ->columnSpanFull(),
                        ])
                        ->compact(),

                    // ===== Media (Gallery) =====
                    Section::make('Media (Gallery)')
                        ->schema([
                            Repeater::make('images')
                                ->relationship('images')
                                ->schema([
                                    Grid::make(12)->schema([
                                        FileUpload::make('file_path')
                                            ->image()
                                            ->directory('products')
                                            ->disk('public')
                                            ->imageEditor()
                                            ->columnSpan(6)
                                            ->helperText('Jalankan: php artisan storage:link'),

                                        TextInput::make('alt')->columnSpan(6),
                                        TextInput::make('position')->numeric()->columnSpan(3),
                                        Toggle::make('is_primary')->label('Primary')->columnSpan(3),
                                    ]),
                                ])
                                ->columns(1)
                                ->createItemButtonLabel('Add image')
                                ->collapsible(),
                        ])
                        ->compact(),

                    // ===== Pricing (mirip contoh) =====
                    // Section::make('Pricing')
                    //     ->schema([
                    //         Grid::make(12)->schema([
                    //             TextInput::make('price')
                    //                 ->numeric()
                    //                 ->label('Price')
                    //                 ->prefix('Rp')
                    //                 ->columnSpan(4),

                    //             TextInput::make('compare_at_price')
                    //                 ->numeric()
                    //                 ->label('Compare at')
                    //                 ->prefix('Rp')
                    //                 ->columnSpan(4),

                    //             TextInput::make('cost_price')
                    //                 ->numeric()
                    //                 ->label('Cost')
                    //                 ->prefix('Rp')
                    //                 ->columnSpan(4),
                    //         ]),
                    //     ])
                    //     ->compact(),

                    // ===== Inventory (fallback) + Vendor (dipindah ke kanan -> Associations),
                    // tapi stok fallback tetap di kiri agar dekat konteks harga =====
                    // Section::make('Inventory (fallback)')
                    //     ->schema([
                    //         Grid::make(12)->schema([
                    //             TextInput::make('inventory_quantity')
                    //                 ->numeric()
                    //                 ->default(0)
                    //                 ->dehydrateStateUsing(fn ($state) => (int) ($state ?? 0))
                    //                 ->label('Stock (fallback)')
                    //                 ->columnSpan(4),
                    //         ]),
                    //     ])
                    //     ->compact(),

                    // ===== Shipping & Tax =====
                    // Section::make('Shipping & Tax')
                    //     ->schema([
                    //         Fieldset::make('')->schema([
                    //             Toggle::make('requires_shipping')->label('Requires shipping')->default(true),
                    //             Toggle::make('taxable')->label('Taxable')->default(true),
                    //             TextInput::make('weight')->numeric()->label('Weight'),
                    //             Select::make('weight_unit')->options([
                    //                 'g' => 'g',
                    //                 'kg' => 'kg',
                    //                 'oz' => 'oz',
                    //                 'lb' => 'lb',
                    //             ])->default('g'),
                    //         ])->columns(4),
                    //     ])
                    //     ->compact(),

                    // ===== Variants (Options → Variants) =====
                    Section::make('Variants (Options → Variants)')
                        ->schema([
                            Repeater::make('options_schema')->label('Options')->schema([
                                TextInput::make('name')->required(),
                                Forms\Components\TagsInput::make('values')->required()->splitKeys([',', 'Enter']),
                            ])->maxItems(3)->columns(1)->reorderable()->live(onBlur: true),

                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('syncVariants')
                                    ->label('Sync variants from options')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(function (Get $get, Set $set) {
                                        $options = $get('options_schema') ?: [];
                                        $options = array_values(array_filter(array_map(function ($o) {
                                            return [
                                                'name'   => trim((string) ($o['name'] ?? '')),
                                                'values' => array_values(array_filter(array_map('trim', (array) ($o['values'] ?? [])))),
                                            ];
                                        }, $options), fn ($o) => $o['name'] !== '' && !empty($o['values'])));
                                        $options = array_slice($options, 0, 3);

                                        $set('option1_name', $options[0]['name'] ?? null);
                                        $set('option2_name', $options[1]['name'] ?? null);
                                        $set('option3_name', $options[2]['name'] ?? null);

                                        $lists  = [$options[0]['values'] ?? [null], $options[1]['values'] ?? [null], $options[2]['values'] ?? [null]];
                                        $combos = [[]];
                                        foreach ($lists as $vals) {
                                            $tmp = [];
                                            foreach ($combos as $c) {
                                                foreach ($vals as $v) {
                                                    $nc   = $c;
                                                    $nc[] = $v;
                                                    $tmp[] = $nc;
                                                }
                                            }
                                            $combos = $tmp;
                                        }

                                        $existing = $get('variants') ?: [];
                                        $find = function ($o1, $o2, $o3) use ($existing) {
                                            foreach ($existing as $ex) {
                                                if (($ex['option1_value'] ?? null) === $o1
                                                    && ($ex['option2_value'] ?? null) === $o2
                                                    && ($ex['option3_value'] ?? null) === $o3) {
                                                    return $ex;
                                                }
                                            }
                                            return null;
                                        };

                                        $result = [];
                                        foreach ($combos as $c) {
                                            [$o1, $o2, $o3] = [$c[0] ?? null, $c[1] ?? null, $c[2] ?? null];
                                            $title = trim(implode(' / ', array_filter([$o1, $o2, $o3])));
                                            $prev  = $find($o1, $o2, $o3);

                                            $result[] = [
                                                'title'                     => $title ?: 'Default',
                                                'option1_value'             => $o1,
                                                'option2_value'             => $o2,
                                                'option3_value'             => $o3,
                                                'sku'                       => $prev['sku'] ?? null,
                                                'barcode'                   => $prev['barcode'] ?? null,
                                                'price'                     => $prev['price'] ?? null,
                                                'compare_at_price'          => $prev['compare_at_price'] ?? null,
                                                'inventory_quantity'        => $prev['inventory_quantity'] ?? 0,
                                                'requires_shipping'         => $prev['requires_shipping'] ?? true,
                                                'taxable'                   => $prev['taxable'] ?? true,
                                                'weight'                    => $prev['weight'] ?? null,
                                                'weight_unit'               => $prev['weight_unit'] ?? null,
                                                'shopify_variant_id'        => $prev['shopify_variant_id'] ?? null,
                                                'shopify_inventory_item_id' => $prev['shopify_inventory_item_id'] ?? null,
                                                'product_image_id'          => $prev['product_image_id'] ?? null,
                                            ];
                                        }

                                        $set('variants', $result);
                                        Notification::make()->success()->title('Variants synced')->send();
                                    }),
                            ]),
                        ])
                        ->collapsible()
                        ->compact(),

                    // ===== Variants list =====
                    Section::make('Variants list')
                        ->schema([
                            Repeater::make('variants')->relationship('variants')->schema([
                                Grid::make(12)->schema([
                                    TextInput::make('title')->label('Title')->columnSpan(4),
                                    TextInput::make('option1_value')->label('Option 1')->columnSpan(4),
                                    TextInput::make('option2_value')->label('Option 2')->columnSpan(4),
                                    TextInput::make('option3_value')->label('Option 3')->columnSpan(4),

                                    TextInput::make('sku')->label('SKU')->columnSpan(3),
                                    TextInput::make('barcode')->label('Barcode')->columnSpan(3),

                                    TextInput::make('price')->numeric()
                                        ->dehydrateStateUsing(fn ($state) => $state === '' ? null : $state)
                                        ->label('Price')->columnSpan(3),

                                    TextInput::make('compare_at_price')->numeric()
                                        ->dehydrateStateUsing(fn ($state) => $state === '' ? null : $state)
                                        ->label('Compare at')->columnSpan(3),

                                    TextInput::make('inventory_quantity')->numeric()->default(0)
                                        ->dehydrateStateUsing(fn ($state) => (int) ($state ?? 0))
                                        ->label('Stock')->columnSpan(3),

                                    Toggle::make('requires_shipping')->label('Ship')->default(true)->columnSpan(2),
                                    Toggle::make('taxable')->label('Tax')->default(true)->columnSpan(2),

                                    TextInput::make('weight')->numeric()->label('Weight')->columnSpan(2),
                                    Select::make('weight_unit')->options(['g' => 'g', 'kg' => 'kg', 'oz' => 'oz', 'lb' => 'lb'])->columnSpan(2),

                                    FileUpload::make('tmp_image')->label('Upload image (optional)')
                                        ->image()->directory('products')->visibility('public')->imageEditor()
                                        ->helperText('Jika diunggah di sini, sistem akan membuat ProductImage & mengaitkannya ke varian setelah disimpan.')
                                        ->columnSpan(6),

                                    TextInput::make('shopify_variant_id')->disabled()->dehydrated(false)->columnSpan(6),
                                    TextInput::make('shopify_inventory_item_id')->disabled()->dehydrated(false)->columnSpan(6),
                                ]),
                            ])->columns(1)->collapsible()->createItemButtonLabel('Add variant'),
                        ])
                        ->collapsed()
                        ->compact(),

                    // ===== Sync (Read-only) =====
                    Section::make('Sync (Read-only)')
                        ->schema([
                            Grid::make(12)->schema([
                                TextInput::make('shopify_product_id')->disabled()->dehydrated(false)->columnSpan(4),
                                TextInput::make('sync_status')->disabled()->dehydrated(false)->columnSpan(4),
                                TextInput::make('last_synced_at')->disabled()->dehydrated(false)->columnSpan(4),
                            ]),
                        ])
                        ->compact(),

                ])->columnSpan(8),

                /* =========================
                 *  KANAN (sidebar) 4/12
                 * ========================= */
                Group::make()->schema([

                    // ===== Status / Availability =====
                    Section::make('Status')
                        ->schema([
                            Select::make('status')
                                ->options(['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'])
                                ->required()
                                ->columnSpanFull(),

                            DateTimePicker::make('published_at')
                                ->label('Availability')
                                ->native(false)
                                ->seconds(false)
                                ->helperText('Biarkan kosong untuk auto saat Active')
                                ->columnSpanFull(),
                        ])
                        ->compact(),

                    // ===== Associations / Organization =====
                    Section::make('Associations')
                        ->schema([
                            Select::make('category_id')
                                ->label('Category')
                                ->relationship('category', 'name')
                                ->options(fn () => Category::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->nullable()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')->required(),
                                    Forms\Components\TextInput::make('shopify_category')->label('Shopify Category (optional)'),
                                ])
                                ->createOptionAction(fn ($action) => $action->modalHeading('Add Category')),

                            TextInput::make('product_type')->label('Product Type'),
                            TextInput::make('vendor')->label('Vendor'),

                            // Pindahkan tag ke sidebar (sesuai permintaan)
                            TextInput::make('tags')->label('Tags (comma separated)'),
                        ])
                        ->compact(),

                    // ===== SEO =====
                    Section::make('SEO')
                        ->schema([
                            TextInput::make('seo_title')->maxLength(70),
                            Textarea::make('seo_description')->maxLength(320),
                        ])
                        ->compact(),

                ])->columnSpan(4),

            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Thumbnail gambar
                ImageColumn::make('thumbnail')
                    ->label(' ')
                    ->getStateUsing(function (Product $record) {
                        $img = $record->images()
                            ->orderByDesc('is_primary')
                            ->orderByRaw('COALESCE(position, 9999) asc')
                            ->first();

                        if (!$img) {
                            return null;
                        }

                        $path = (string) $img->file_path;

                        if (Str::startsWith($path, ['http://', 'https://'])) {
                            return $path;
                        }

                        return url('/storage/' . ltrim($path, '/'));
                    })
                    ->height(36)
                    ->square(),

                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(40),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => is_null($state) ? '-' : 'Rp ' . number_format((float) $state, 0, ',', '.')),
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
            ->filters([
                SelectFilter::make('status')->options([
                    'active'   => 'Active',
                    'draft'    => 'Draft',
                    'archived' => 'Archived',
                ]),
                Filter::make('low_stock')
                    ->label('Stok < 5')
                    ->query(fn (EloquentBuilder $q) => $q->where('inventory_quantity', '<', 5)),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100])

            // Row actions
            ->actions([
                // Tables\Actions\Action::make('toggleSync')
                //     ->label(null)
                //     ->icon(fn ($record) => $record->sync_enabled ? 'heroicon-o-link-slash' : 'heroicon-o-link')
                //     ->color(fn ($record) => $record->sync_enabled ? 'warning' : 'success')
                //     ->iconButton()
                //     ->size(ActionSize::Small)
                //     ->tooltip(fn ($record) => $record->sync_enabled ? 'Putus Sinkronisasi' : 'Aktifkan Sinkronisasi')
                //     ->requiresConfirmation()
                //     ->modalHeading(fn ($r) => $r->sync_enabled ? 'Putus sinkronisasi produk ini?' : 'Aktifkan sinkronisasi produk ini?')
                //     ->modalDescription(fn ($r) => $r->sync_enabled
                //         ? 'Produk ini tidak akan lagi diperbarui otomatis ke Shopify sampai Anda mengaktifkannya kembali.'
                //         : 'Produk ini akan kembali mengikuti pembaruan otomatis ke Shopify.')
                //     ->modalSubmitActionLabel('Lanjutkan')
                //     ->action(function ($record) {
                //         $record->sync_enabled = !$record->sync_enabled;
                //         $record->saveQuietly();
                //         Notification::make()->title('Status sinkronisasi diperbarui')->success()->send();
                //     }),

                // Tables\Actions\Action::make('pushToShopify')
                //     ->label(null)
                //     ->icon('heroicon-o-cloud-arrow-up')
                //     ->color('primary')
                //     ->iconButton()
                //     ->size(ActionSize::Small)
                //     ->tooltip('Push ke Shopify')
                //     ->visible(fn ($record) => $record->sync_enabled)
                //     ->requiresConfirmation()
                //     ->modalHeading('Kirim pembaruan ke Shopify?')
                //     ->modalDescription('Tindakan ini akan memperbarui produk di Shopify sesuai data saat ini.')
                //     ->modalSubmitActionLabel('Push sekarang')
                //     ->action(fn ($record) => app(\App\Services\ShopifyPushService::class)->updateOnShopify($record)),

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
                    ->action(function (Product $record) {
                        try {
                            if ($record->shopify_product_id) {
                                app(\App\Services\ShopifyPushService::class)->deleteOnShopify($record);
                            }
                        } catch (\Throwable $e) {
                            // optional: log error
                        }
                        $record->delete();
                        Notification::make()->title('Produk dihapus')->body('Dihapus dari Shopify (jika ada) dan database.')->success()->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label(null)
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->size(ActionSize::Small)
                    ->tooltip('Edit'),
            ])

            // Bulk actions
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
                                $rec->sync_enabled = !$rec->sync_enabled;
                                $rec->saveQuietly();
                            }
                            Notification::make()->title('Sinkronisasi diperbarui')->body('Status sync untuk produk terpilih telah diperbarui.')->success()->send();
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
                            Notification::make()->title('Produk dipush')->body('Produk terpilih telah dipush ke Shopify (jika sinkron).')->success()->send();
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
                                    // lanjut hapus offline meski gagal hapus di Shopify
                                }
                                $rec->delete();
                            }
                            Notification::make()->title('Produk terpilih dihapus')->body('Dihapus dari Shopify (jika ada) dan database.')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ])
                    ->label('Aksi')
                    ->color('gray')
                    ->icon('heroicon-o-sparkles')
                    ->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
