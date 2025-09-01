<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\ShopifyPushService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    /** Nama tampilan rapi dari varian (tanpa "Default/Default Title") */
    private static function displayNameFromVariant(?ProductVariant $v): ?string
    {
        if (!$v) return null;
        if (!$v->relationLoaded('product')) $v->load('product');

        $p  = trim((string) ($v->product->title ?? ''));
        $vv = trim((string) ($v->title ?? ''));
        $isDefault = $vv === '' || strcasecmp($vv, 'Default') === 0 || strcasecmp($vv, 'Default Title') === 0;

        if ($p !== '' && !$isDefault && $vv !== '') return $p . ' / ' . $vv;
        return $p !== '' ? $p : ($isDefault ? null : $vv);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()->success()->title('Penjualan tersimpan');
    }

    protected function afterCreate(): void
    {
        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
    }

    /** Helper: hitung ringkasan dan set semua field terkait */
    private static function recalcSummary(Set $set, Get $get): void
    {
        $items = $get('items') ?? [];

        $subtotal = collect($items)->sum(
            fn ($i) => (float)($i['price'] ?? 0) * max(0, (int)($i['qty'] ?? 0))
        );
        $units = collect($items)->sum(fn ($i) => max(0, (int)($i['qty'] ?? 0)));
        $lines = count(array_filter($items, fn ($i) => !empty($i['product_id'])));

        // persen (clamp 0..100)
        $discountPct = max(0, min(100, (float)($get('discount_pct') ?? 0)));
        $taxPct      = max(0, min(100, (float)($get('tax_pct') ?? 0)));

        $discountAmount = round($subtotal * ($discountPct / 100), 2);
        $taxBase        = max(0, $subtotal - $discountAmount);          // pajak setelah diskon
        $taxAmount      = round($taxBase * ($taxPct / 100), 2);

        $total = max(0, $subtotal - $discountAmount + $taxAmount);
        $paid  = (float)($get('paid_amount') ?? 0);
        $change = max(0, $paid - $total);

        $set('total_units', $units);
        $set('items_count', $lines);

        $set('subtotal', number_format($subtotal, 2, '.', ''));
        $set('discount_amount', number_format($discountAmount, 2, '.', ''));
        $set('tax_amount', number_format($taxAmount, 2, '.', ''));
        $set('total', number_format($total, 2, '.', ''));
        $set('change_amount', number_format($change, 2, '.', ''));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(12)->schema([
                /* =================== KIRI (8/12): ITEMS =================== */
                Group::make()->schema([
                    Section::make('Items')
                        ->schema([
                            Repeater::make('items')
                                ->defaultItems(1)
                                ->columns(12) // tampil mendatar per baris
                                ->collapsible()
                                ->itemLabel(function (array $state): ?string {
                                    if (!empty($state['product_variant_id'])) {
                                        $v = ProductVariant::with('product')->find($state['product_variant_id']);
                                        return self::displayNameFromVariant($v);
                                    }
                                    if (!empty($state['product_id'])) {
                                        return Product::find($state['product_id'])?->title;
                                    }
                                    return null;
                                })
                                ->schema([
                                    // 1) Product (search)
                                    Select::make('product_id')
                                        ->label('Product')
                                        ->searchable()
                                        ->columnSpan(5)
                                        ->getSearchResultsUsing(function (string $search): array {
                                            $driver = DB::connection()->getDriverName();
                                            $like = $driver === 'pgsql' ? 'ilike' : 'like';

                                            return Product::query()
                                                ->where(function ($q) use ($search, $like) {
                                                    $q->where('title', $like, "%{$search}%")
                                                      ->orWhere('vendor', $like, "%{$search}%");
                                                })
                                                ->orderBy('title')
                                                ->limit(50)
                                                ->pluck('title', 'id')
                                                ->all();
                                        })
                                        ->getOptionLabelUsing(fn ($value) => $value ? Product::find($value)?->title : null)
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            // reset saat product berubah
                                            $set('product_variant_id', null);
                                            $set('price', null);
                                            $set('qty', null);
                                            self::recalcSummary($set, $get);
                                        })
                                        ->required(),

                                    // 2) Varian (tergantung product)
                                    Select::make('product_variant_id')
                                        ->label('Varian')
                                        ->columnSpan(4)
                                        ->native(false)
                                        ->options(function (Get $get): array {
                                            $pid = $get('product_id');
                                            if (!$pid) return [];
                                            return ProductVariant::query()
                                                ->where('product_id', $pid)
                                                ->orderBy('title')
                                                ->get()
                                                ->mapWithKeys(function ($v) {
                                                    $var = trim((string) ($v->title ?? ''));
                                                    $label = $var !== '' && strcasecmp($var, 'Default Title') !== 0 ? $var : 'Default';
                                                    return [$v->id => $label];
                                                })
                                                ->all();
                                        })
                                        ->disabled(fn (Get $get) => blank($get('product_id')))
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            // set harga dari varian & recalc
                                            $price = 0;
                                            if ($state) {
                                                $v = ProductVariant::find($state);
                                                $price = (float)($v->price ?? 0);
                                            }
                                            $qty = max(1, (int)($get('qty') ?: 1));
                                            $set('price', $price);
                                            $set('qty', $qty);
                                            self::recalcSummary($set, $get);
                                        })
                                        ->required(),

                                    // 3) Harga (auto & read-only)
                                    TextInput::make('price')
                                        ->label('Harga')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated(true)
                                        ->columnSpan(2),

                                    // 4) Jumlah
                                    TextInput::make('qty')
                                        ->label('Jumlah')
                                        ->numeric()
                                        ->default(1)
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            $qty = max(1, (int)$state);
                                            $set('qty', $qty);
                                            self::recalcSummary($set, $get);
                                        })
                                        ->required()
                                        ->columnSpan(1),
                                ])
                                // Fallback: bila state lain berubah
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    self::recalcSummary($set, $get);
                                }),
                        ]),
                ])->columnSpan(8),

                /* =================== KANAN (4/12): SIDEBAR =================== */
                Group::make()->schema([
                    Section::make('Order')
                        ->columns(1)
                        ->schema([
                            TextInput::make('number')->label('No. Nota')->disabled()
                                ->default(fn () => 'POS-' . now()->format('Ymd') . '-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT)),
                        ]),

                    Section::make('Customer & Payment')
                        ->columns(1)
                        ->schema([
                            TextInput::make('customer_name')->label('Pelanggan')->maxLength(120),
                            Select::make('payment_method')->label('Metode Pembayaran')->options([
                                'cash' => 'Cash', 'card' => 'Kartu', 'qris' => 'QRIS', 'transfer' => 'Transfer',
                            ])->default('cash'),
                        ]),

                    // ===== Ringkasan: persen & nilai rupiah =====
                    Section::make('Ringkasan')
                        ->columns(2)
                        ->schema([
                            TextInput::make('total_units')->label('Total Qty')->disabled(),
                            TextInput::make('items_count')->label('Jenis Produk')->disabled(),

                            TextInput::make('subtotal')->label('Subtotal')->disabled()->columnSpan(2),

                            // Diskon dalam persen -> tampilkan juga nominal
                            TextInput::make('discount_pct')
                                ->label('Diskon (%)')
                                ->numeric()->minValue(0)->maxValue(100)
                                ->suffix('%')
                                ->default(0)
                                ->reactive()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    $set('discount_pct', max(0, min(100, (float)$state)));
                                    self::recalcSummary($set, $get);
                                }),
                            TextInput::make('discount_amount')
                                ->label('Diskon (Rp)')
                                ->disabled(),

                            // Pajak dalam persen -> tampilkan juga nominal
                            TextInput::make('tax_pct')
                                ->label('Pajak (%)')
                                ->numeric()->minValue(0)->maxValue(100)
                                ->suffix('%')
                                ->default(0)
                                ->reactive()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    $set('tax_pct', max(0, min(100, (float)$state)));
                                    self::recalcSummary($set, $get);
                                }),
                            TextInput::make('tax_amount')
                                ->label('Pajak (Rp)')
                                ->disabled(),

                            TextInput::make('total')->label('Total')->disabled()->columnSpan(2),

                            TextInput::make('paid_amount')->label('Dibayar')->numeric()->default(0)->reactive()
                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                    // hanya update kembalian, tidak mengubah kalkulasi lain
                                    $paid  = (float)$state;
                                    $total = (float)($get('total') ?? 0);
                                    $set('change_amount', number_format(max(0, $paid - $total), 2, '.', ''));
                                }),

                            TextInput::make('change_amount')->label('Kembalian')->disabled()->columnSpan(2),
                        ]),
                ])->columnSpan(4),
            ]),
        ]);
    }

    /** Simpan + kurangi stok lokal, kemudian sinkron stok ke Shopify */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $items = $data['items'] ?? [];

        // Hitung ulang: subtotal dan units
        $subtotal = 0.0; $totalUnits = 0;
        foreach ($items as $i) {
            $qty   = max(1, (int)($i['qty'] ?? 0));
            $price = (float)($i['price'] ?? 0);
            $subtotal   += $qty * $price;
            $totalUnits += $qty;
        }

        // Ambil persen dari form (default 0..100)
        $discountPct = max(0, min(100, (float)($data['discount_pct'] ?? 0)));
        $taxPct      = max(0, min(100, (float)($data['tax_pct'] ?? 0)));

        // Konversi ke nominal rupiah utk disimpan di DB (kompatibel dgn receipt)
        $discountAmount = round($subtotal * ($discountPct / 100), 2);
        $taxBase        = max(0, $subtotal - $discountAmount);     // pajak dihitung setelah diskon
        $taxAmount      = round($taxBase * ($taxPct / 100), 2);

        $total  = max(0, $subtotal - $discountAmount + $taxAmount);
        $paid   = (float)($data['paid_amount'] ?? 0);
        $change = max(0, $paid - $total);
        $status = $paid >= $total ? 'paid' : 'unpaid';
        $userId = optional(Auth::user())->id;

        $productIds = [];

        $sale = DB::transaction(function () use ($data, $items, $subtotal, $discountAmount, $taxAmount, $total, $paid, $change, $status, $userId, &$productIds) {
            $sale = Sale::create([
                'number'          => $data['number'] ?? ('POS-' . now()->format('Ymd-His')),
                'cashier_user_id' => $userId,
                'customer_name'   => $data['customer_name'] ?? null,
                'subtotal'        => $subtotal,
                'discount'        => $discountAmount,  // simpan sebagai nominal
                'tax'             => $taxAmount,       // simpan sebagai nominal
                'total'           => $total,
                'paid_amount'     => $paid,
                'change_amount'   => $change,
                'payment_method'  => $data['payment_method'] ?? 'cash',
                'status'          => $status,
            ]);

            foreach ($items as $row) {
                $qty = max(1, (int)($row['qty'] ?? 0));

                // pastikan ada varian
                $variant = null;
                if (!empty($row['product_variant_id'])) {
                    $variant = ProductVariant::with('product')->find($row['product_variant_id']);
                } elseif (!empty($row['product_id'])) {
                    $variant = ProductVariant::with('product')->where('product_id', $row['product_id'])->orderBy('id')->first();
                }

                $price = (float)($row['price'] ?? ($variant->price ?? 0));
                $line  = $qty * $price;

                // nama rapi
                $computed = self::displayNameFromVariant($variant);
                $sku      = $variant?->sku;
                $name     = $computed ?: ($sku ?: 'Item');

                SaleItem::create([
                    'sale_id'            => $sale->id,
                    'product_variant_id' => $variant?->id,
                    'sku'                => $sku,
                    'name'               => $name,
                    'price'              => $price,
                    'qty'                => $qty,
                    'line_total'         => $line,
                ]);

                // kurangi stok
                if ($variant) {
                    if (!is_null($variant->inventory_quantity)) {
                        $variant->decrement('inventory_quantity', $qty);
                    }
                    if ($variant->product && !is_null($variant->product->inventory_quantity)) {
                        $variant->product->decrement('inventory_quantity', $qty);
                    }
                    if ($variant->product_id) {
                        $productIds[$variant->product_id] = true;
                    }
                }
            }

            return $sale->fresh('items');
        });

        // sinkron stok ke Shopify
        if (!empty($productIds)) {
            try {
                $svc = app(ShopifyPushService::class);
                $products = Product::whereIn('id', array_keys($productIds))->get();
                foreach ($products as $p) $svc->pushInventoryLevels($p);
            } catch (\Throwable $e) {
                report($e); // tidak menggagalkan transaksi
            }
        }

        return $sale;
    }
}
