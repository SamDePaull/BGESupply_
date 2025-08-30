<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Transaksi')
                    ->columns(3)
                    ->schema([
                        TextInput::make('number')
                            ->label('No. Nota')
                            ->disabled()
                            ->default(fn () => 'POS-'.now()->format('Ymd').'-'.str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT)),
                        TextInput::make('customer_name')->label('Pelanggan')->maxLength(120),
                        Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash' => 'Cash',
                                'card' => 'Kartu',
                                'qris' => 'QRIS',
                                'transfer' => 'Transfer',
                            ])
                            ->default('cash'),
                    ]),

                 Section::make('Items')
                    ->schema([
                         Repeater::make('items')
                            ->relationship() // Sale::items()
                            ->defaultItems(1)
                            ->grid(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->schema([
                                 Select::make('product_variant_id')
                                    ->label('Produk / Varian')
                                    ->searchable()
                                    ->preload()
                                    ->options(function (string $search = null) {
                                        $q = ProductVariant::query()
                                            ->select('product_variants.id')
                                            ->addSelect(DB::raw("COALESCE(product_variants.sku, '') || ' — ' || COALESCE(product_variants.title, '') as label"))
                                            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id');
                                        if ($search) {
                                            $q->where(function ($w) use ($search) {
                                                $w->where('product_variants.sku', 'ilike', "%{$search}%")
                                                  ->orWhere('product_variants.title', 'ilike', "%{$search}%")
                                                  ->orWhere('products.title', 'ilike', "%{$search}%");
                                            });
                                        }
                                        return $q->orderBy('label')->limit(50)->pluck('label', 'product_variants.id');
                                    })
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (!$state) return;
                                        $v = ProductVariant::find($state);
                                        if ($v) {
                                            $set('sku', $v->sku);
                                            $set('name', $v->title ?? ($v->product->title ?? ''));
                                            $set('price', $v->price ?? 0);
                                            $set('cost_price', $v->cost_price ?? null);
                                            $qty = (int)($set('qty') ?? 1);
                                            $set('line_total', round(($v->price ?? 0) * max(1, $qty), 2));
                                        }
                                    })
                                    ->required(),

                                TextInput::make('sku')->label('SKU')->disabled(),
                                TextInput::make('name')->label('Nama')->disabled(),

                                TextInput::make('price')
                                    ->label('Harga')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $qty = (int)$get('qty') ?: 1;
                                        $set('line_total', round(($state ?: 0) * $qty, 2));
                                    })
                                    ->required(),

                                TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $price = (float)($get('price') ?: 0);
                                        $set('line_total', round($price * max(1, (int)$state), 2));
                                    })
                                    ->required(),

                                 TextInput::make('line_total')
                                    ->label('Subtotal')
                                    ->disabled(),
                                 Hidden::make('cost_price'),
                            ])
                            ->columns(5),
                    ]),

                 Section::make('Ringkasan')
                    ->columns(4)
                    ->schema([
                         TextInput::make('subtotal')->label('Subtotal')->disabled(),
                         TextInput::make('discount')
                            ->label('Diskon')
                            ->numeric()
                            ->default(0)
                            ->reactive(),
                         TextInput::make('tax')
                            ->label('PPN')
                            ->numeric()
                            ->default(0)
                            ->reactive(),
                         TextInput::make('total')->label('Total')->disabled(),

                         TextInput::make('paid_amount')
                            ->label('Dibayar')
                            ->numeric()
                            ->default(0)
                            ->reactive(),
                         TextInput::make('change_amount')->label('Kembalian')->disabled(),
                    ])
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        // kalkulasi subtotal/total tiap perubahan section
                        $items = $get('items') ?? [];
                        $subtotal = collect($items)->sum(fn ($i) => (float)($i['line_total'] ?? 0));
                        $discount = (float)($get('discount') ?? 0);
                        $tax = (float)($get('tax') ?? 0);
                        $total = max(0, $subtotal - $discount + $tax);
                        $paid = (float)($get('paid_amount') ?? 0);
                        $change = max(0, $paid - $total);

                        $set('subtotal', number_format($subtotal, 2, '.', ''));
                        $set('total', number_format($total, 2, '.', ''));
                        $set('change_amount', number_format($change, 2, '.', ''));
                    }),
            ]);
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Hitung terakhir sebelum simpan
        $items = $data['items'] ?? [];
        $subtotal = collect($items)->sum(fn ($i) => (float)($i['line_total'] ?? 0));
        $discount = (float)($data['discount'] ?? 0);
        $tax = (float)($data['tax'] ?? 0);
        $total = max(0, $subtotal - $discount + $tax);
        $paid = (float)($data['paid_amount'] ?? 0);
        $change = max(0, $paid - $total);

        $userId = optional(Auth::user())->id;

        return DB::transaction(function () use ($data, $items, $subtotal, $discount, $tax, $total, $paid, $change, $userId) {
            $sale = Sale::create([
                'number' => $data['number'] ?? ('POS-'.now()->format('Ymd-His')),
                'cashier_user_id' => $userId,
                'customer_name' => $data['customer_name'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid_amount' => $paid,
                'change_amount' => $change,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'status' => 'paid',
            ]);

            foreach ($items as $i) {
                $variantId = $i['product_variant_id'] ?? null;
                $qty = (int)($i['qty'] ?? 1);
                $price = (float)($i['price'] ?? 0);

                $variant = $variantId
                    ? ProductVariant::whereKey($variantId)->lockForUpdate()->first()
                    : null;

                if ($variant) {
                    // validasi stok
                    $current = (int)($variant->inventory_quantity ?? 0);
                    if ($current < $qty) {
                        throw new \RuntimeException("Stok tidak mencukupi untuk SKU {$variant->sku} (stok: {$current}, diminta: {$qty})");
                    }
                    // kurangi stok
                    $variant->inventory_quantity = $current - $qty;
                    $variant->saveQuietly();
                }

                $name = $i['name'] ?? ($variant->title ?? ($variant?->product?->title ?? ''));
                $sku = $i['sku'] ?? ($variant->sku ?? null);
                $line = round($price * max(1, $qty), 2);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_variant_id' => $variant?->id,
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $price,
                    'cost_price' => $i['cost_price'] ?? $variant?->cost_price,
                    'qty' => max(1, $qty),
                    'line_total' => $line,
                ]);
            }

            return $sale;
        });
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Penjualan tersimpan')
            ->success()
            ->send();
    }
}
