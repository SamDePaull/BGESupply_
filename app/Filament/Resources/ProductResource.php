<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 1;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Product Info')
                    ->schema([
                        Grid::make(12)->schema([
                            TextInput::make('title')->label('Title')->required()->maxLength(255)->columnSpan(8),
                            TextInput::make('sku')->label('SKU (fallback)')->maxLength(150)->columnSpan(4),
                        ]),
                        Grid::make(12)->schema([
                            TextInput::make('price')
                                ->numeric()
                                ->label('Price (IDR)')
                                ->prefix('Rp')->columnSpan(4),
                            TextInput::make('inventory_quantity')->numeric()->label('Stock (fallback)')->default(0)->dehydrateStateUsing(fn($state) => (int) ($state ?? 0))->columnSpan(4),
                            TextInput::make('vendor')->label('Vendor')->maxLength(150)->columnSpan(4),
                        ]),
                        TextInput::make('tags')->label('Tags (comma separated)')->maxLength(500),
                        RichEditor::make('description')->label('Description')->columnSpanFull(),
                    ])->collapsible(),
                Section::make('Variants (Shopify-like)')
                    ->schema([
                        // Builder mirip Shopify: Option name + Option values (tags)
                        Repeater::make('options_schema')
                            ->label('Options')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Option name')
                                    ->placeholder('Size / Color / Material')
                                    ->required(),
                                TagsInput::make('values')
                                    ->label('Option values')
                                    ->placeholder('Add another value')
                                    ->required()
                                    ->splitKeys([',', 'Enter'])
                                    ->suggestions([]),
                            ])
                            ->columns(1)
                            ->maxItems(3)
                            ->reorderable()
                            ->collapsible()
                            ->helperText('Maks 3 opsi. Contoh: Size = S,M,L;
Color = Red,Blue; ...')
                            ->live(onBlur: true),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('syncVariants')
                                ->label('Sync variants from options')
                                ->icon('heroicon-o-arrow-path')
                                ->action(function (Get $get, Set $set) {
                                    $options = $get('options_schema') ?: [];
                                    // Normalisasi: ambil max 3 opsi
                                    $options =
                                        array_values(array_filter(array_map(function ($o) {
                                            return [
                                                'name' => trim((string)
                                                ($o['name'] ?? '')),
                                                'values' =>
                                                array_values(array_filter(array_map('trim', (array)($o['values'] ?? []))))
                                            ];
                                        }, $options), fn($o) => $o['name'] !== ''
                                            && !empty($o['values'])));
                                    $options = array_slice($options, 0, 3);
                                    // Set nama opsi ke kolom produk
                                    $set('option1_name', $options[0]['name'] ??
                                        null);
                                    $set('option2_name', $options[1]['name'] ??
                                        null);
                                    $set('option3_name', $options[2]['name'] ??
                                        null);
                                    // Bangun kombinasi
                                    $lists = [
                                        $options[0]['values'] ?? [null],
                                        $options[1]['values'] ?? [null],
                                        $options[2]['values'] ?? [null],
                                    ];
                                    $combos = [[]];
                                    foreach ($lists as $vals) {
                                        $tmp = [];
                                        foreach ($combos as $c) {
                                            foreach ($vals as $v) {
                                                $nc = $c;
                                                $nc[] = $v;
                                                $tmp[] = $nc;
                                            }
                                        }
                                        $combos = $tmp;
                                    }
                                    // Ambil varian lama agar bisa merge (pertahankan sku/price/qty/id)
                                    $existing = $get('variants') ?: [];
                                    $findExisting = function ($o1, $o2, $o3) use ($existing) {
                                        foreach ($existing as $ex) {
                                            if (($ex['option1_value'] ?? null)
                                                === $o1
                                                && ($ex['option2_value'] ??
                                                    null) === $o2
                                                && ($ex['option3_value'] ??
                                                    null) === $o3
                                            ) return $ex;
                                        }
                                        return null;
                                    };
                                    $result = [];
                                    foreach ($combos as $c) {
                                        [$o1, $o2, $o3] = [$c[0] ?? null, $c[1] ??
                                            null, $c[2] ?? null];
                                        $title = trim(implode(
                                            ' / ',
                                            array_filter([$o1, $o2, $o3], fn($x) => $x !== null && $x !== '')
                                        ));
                                        $prev = $findExisting($o1, $o2, $o3);
                                        $result[] = [
                                            'title' => $title ?: 'Default',
                                            'option1_value' => $o1,
                                            'option2_value' => $o2,
                                            'option3_value' => $o3,
                                            'sku' => $prev['sku'] ?? null,
                                            'price' => $prev['price'] ?? null,
                                            'inventory_quantity' =>
                                            $prev['inventory_quantity'] ?? 0,
                                            'shopify_variant_id' =>
                                            $prev['shopify_variant_id'] ?? null,
                                            'shopify_inventory_item_id' =>
                                            $prev['shopify_inventory_item_id'] ?? null,
                                        ];
                                    }
                                    $set('variants', $result);
                                    Notification::make()->success()->title('Variants synced from options')->send();
                                })
                                ->color('primary')
                        ]),
                    ])->collapsible(),
                Section::make('Variants list')
                    ->schema([
                        Repeater::make('variants')
                            ->relationship('variants')
                            ->schema([
                                Grid::make(12)->schema([
                                    TextInput::make('title')->label('Title')->columnSpan(4),
                                    TextInput::make('option1_value')->label('Option 1 value')->columnSpan(4),
                                    TextInput::make('option2_value')->label('Option 2 value')->columnSpan(4),
                                    TextInput::make('option3_value')->label('Option 3 value')->columnSpan(4),
                                    TextInput::make('sku')->label('SKU')->columnSpan(4),
                                    TextInput::make('price')->numeric()->label('Price')->columnSpan(2),
                                    TextInput::make('inventory_quantity')->numeric()->label('Stock')->default(0)->dehydrateStateUsing(fn($state) => (int) ($state ?? 0))->columnSpan(2),
                                    TextInput::make('shopify_variant_id')->label('Shopify Variant ID')->disabled()->dehydrated(false)->columnSpan(6),
                                    TextInput::make('shopify_inventory_item_id')->label('Inventory Item ID')->disabled()->dehydrated(false)->columnSpan(6),
                                ]),
                            ])
                            ->columns(1)
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string =>
                            $state['title'] ?? null)
                            ->createItemButtonLabel('Add variant'),
                    ])->collapsed(),
                Fieldset::make('Sync (Read-only)')->schema([
                    TextInput::make('shopify_product_id')->label('Shopify
Product ID')->disabled()->dehydrated(false),
                    TextInput::make('sync_status')->label('Sync Status')->disabled()->dehydrated(false),
                    TextInput::make('last_synced_at')->label('Last Synced At')->disabled()->dehydrated(false),
                ])->columns(3),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Title')->searchable()->sortable()->limit(40),
                Tables\Columns\TextColumn::make('sku')->badge()->searchable()->sortable(),
                Tables\Columns\TextColumn::make('price')->label('Price')
                    ->sortable()
                    ->formatStateUsing(fn($state) => is_null($state) ? '-' :
                        'Rp ' . number_format((float)$state, 0, ',', '.')),
                Tables\Columns\TextColumn::make('inventory_quantity')->label('Stock')->sortable(),
                Tables\Columns\TextColumn::make('sync_status')
                    ->label('Sync')->badge()
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'dirty',
                        'success' => 'synced',
                        'danger' => 'failed',
                    ])->sortable(),
                Tables\Columns\TextColumn::make('shopify_product_id')->label('Shopify ID')->copyable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d M Y H:i')->label('Updated')->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('pushToShopify')
                    ->label('Push to Shopify')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->visible(fn(Product $record) => empty($record->shopify_product_id))
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        dispatch(new \App\Jobs\PushProductToShopify($record->id))->onQueue('shopify');
                        Notification::make()->success()->title('Enqueued')->send();
                    }),
                Tables\Actions\Action::make('pushUpdates')
                    ->label('Push Updates')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        dispatch(new \App\Jobs\UpdateProductOnShopify($record->id))->onQueue('shopify');
                        Notification::make()->success()->title('Enqueued')->send();
                    }),
                // DELETE dengan pilihan cakupan
                Tables\Actions\Action::make('deleteScoped')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->modalHeading('Delete product')
                    ->modalDescription('Pilih cakupan penghapusan. Aksi ini tidak dapat dibatalkan.')
                    ->form([
                        \Filament\Forms\Components\Radio::make('scope')
                            ->label('Delete scope')
                            ->options([
                                'shopify' => 'Hapus di Shopify SAJA (tetap ada di website)',
                                'both' => 'Hapus di Shopify & dari website (local DB)',
                            ])
                            ->default('shopify')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Product $record, array $data) {
                        $scope = $data['scope'] ?? 'shopify';
                        if (!empty($record->shopify_product_id)) {
                            dispatch(new \App\Jobs\DeleteProductOnShopify(
                                productId: $record->id,
                                shopifyProductId: (int) $record->shopify_product_id,
                                deleteLocal: $scope === 'both'
                            ))->onQueue('shopify');
                        } else {
                            if ($scope === 'both') {
                                $record->variants()->delete();
                                $record->delete();
                            }
                        }
                        Notification::make()->success()->title('Delete enqueued')->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
            
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
