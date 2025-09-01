<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\MidtransService;
use App\Services\ReceiptService;
use App\Services\WhatsAppService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Filament\Forms\Get;
use Filament\Forms\Set;

class Cashier extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static string $view = 'filament.pages.cashier';
    protected static ?string $navigationGroup = 'Transaksi';
    protected static ?int $navigationSort = 10;
    protected static bool $shouldRegisterNavigation = false;

    public ?array $items = []; // [{product_id, variant_id, qty, price, sub}]
    public ?string $customer_name = null;
    public ?string $customer_phone = null;
    public ?string $payment_method = 'cash'; // cash|midtrans

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Repeater::make('items')
                ->label('Items')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Produk')
                        ->searchable()
                        ->preload()
                        ->options(fn() => Product::orderBy('title')->pluck('title', 'id'))
                        ->reactive()
                        ->afterStateUpdated(function ($state, Set $set) {
                            // default price dari produk jika belum pilih varian
                            $price = optional(Product::find($state))->price;
                            $set('price', $price);
                            $set('variant_id', null);
                            $set('qty', 1);
                        })
                        ->required(),
                    Forms\Components\Select::make('variant_id')
                        ->label('Varian')
                        ->options(function (Get $get) {
                            $pid = $get('product_id');
                            if (!$pid) return [];
                            return ProductVariant::where('product_id', $pid)->orderBy('id')
                                ->get()
                                ->mapWithKeys(fn($v) => [$v->id => trim($v->title ?? ($v->option1_value . ' ' . $v->option2_value . ' ' . $v->option3_value) ?? 'Default')])
                                ->toArray();
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                            if ($state) {
                                $v = ProductVariant::find($state);
                                $set('price', $v?->price ?? $get('price'));
                            }
                        }),
                    Forms\Components\TextInput::make('qty')->numeric()->minValue(1)->default(1)->required()->reactive()
                        ->afterStateUpdated(fn(Get $get, Set $set) => $set('subtotal', (int)$get('qty') * (float)($get('price') ?? 0))),
                    Forms\Components\TextInput::make('price')->numeric()->minValue(0)->prefix('Rp')->reactive()
                        ->afterStateUpdated(fn(Get $get, Set $set) => $set('subtotal', (int)$get('qty') * (float)($get('price') ?? 0))),
                    Forms\Components\Placeholder::make('subtotal')->content(fn(Get $get) => 'Rp ' . number_format((int)$get('qty') * (float)($get('price') ?? 0), 0, ',', '.')),
                ])
                ->columns(5)
                ->createItemButtonLabel('Tambah Item')
                ->default([])
                ->reactive(),
            Forms\Components\TextInput::make('customer_name')->label('Nama Pelanggan'),
            Forms\Components\TextInput::make('customer_phone')->label('No. WhatsApp')->tel()->helperText('Format E.164, contoh 62812xxxxxxx')->rule('regex:/^62\d{8,15}$/'),
            Forms\Components\Radio::make('payment_method')->options(['cash' => 'Tunai', 'midtrans' => 'Midtrans'])->inline()->default('cash'),
        ];
    }

    public function submit(): void
    {
        $items = $this->items ?? [];
        if (empty($items)) {
            Notification::make()->danger()->title('Tidak ada item')->send();
            return;
        }

        $subtotal = 0;
        foreach ($items as &$it) {
            $qty = (int)($it['qty'] ?? 1);
            $price = (float)($it['price'] ?? 0);
            $it['subtotal'] = $qty * $price;
            $subtotal += $it['subtotal'];
        }

        $sale = Sale::create([
            'invoice_no' => Str::upper(Str::random(8)),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $subtotal,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_method === 'cash' ? 'paid' : 'pending',
            'paid_at' => $this->payment_method === 'cash' ? now() : null,
        ]);

        foreach ($items as $it) {
            $p = Product::find($it['product_id']);
            $v = !empty($it['variant_id']) ? ProductVariant::find($it['variant_id']) : null;

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $p?->id,
                'product_variant_id' => $v?->id,
                'title' => $v?->title ?: $p?->title,
                'sku' => $v?->sku ?: $p?->handle,
                'qty' => (int)$it['qty'],
                'price' => (float)$it['price'],
                'subtotal' => (float)$it['subtotal'],
            ]);
        }

        if ($this->payment_method === 'midtrans') {
            // bikin transaksi
            $payload = [
                'payment_type' => 'qris', // contoh aman; bisa diubah ke 'bank_transfer'/'gopay'/'credit_card' dll
                'transaction_details' => [
                    'order_id' => $sale->invoice_no,
                    'gross_amount' => (int)$sale->total,
                ],
                'customer_details' => [
                    'first_name' => $sale->customer_name ?: 'Customer',
                    'phone' => $sale->customer_phone,
                ],
                'item_details' => array_map(function ($it) {
                    return [
                        'id' => $it['product_id'],
                        'price' => (int)$it['price'],
                        'quantity' => (int)$it['qty'],
                        'name' => substr($it['title'] ?? 'Item', 0, 50),
                    ];
                }, $items),
            ];

            try {
                $resp = app(MidtransService::class)->createTransaction($payload);
                $sale->midtrans_order_id = $resp['order_id'] ?? $sale->invoice_no;
                $sale->midtrans_token = $resp['token'] ?? null;
                $sale->midtrans_redirect_url = $resp['redirect_url'] ?? null;
                $sale->saveQuietly();
                Notification::make()->success()->title('Transaksi dibuat')->body('Selesaikan pembayaran di Midtrans.')->send();
            } catch (\Throwable $e) {
                Notification::make()->danger()->title('Midtrans gagal')->body($e->getMessage())->send();
            }
        } else {
            // cash: buat nota & kirim WA
            try {
                $url = app(ReceiptService::class)->generatePdf($sale);
                if ($this->customer_phone) {
                    app(WhatsAppService::class)->sendText($this->customer_phone, "Terima kasih. Invoice #{$sale->invoice_no}: {$url}");
                }
            } catch (\Throwable $e) {
                // silent log
            }
        }

        // redirect ke halaman products (permintaan poin #6)
        $this->redirect(\App\Filament\Resources\ProductResource::getUrl('index'));
    }
}
