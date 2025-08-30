<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use App\Services\ShopifyService;
use App\Jobs\SyncShopifyProducts;
use App\Models\Product;
use App\Jobs\PushProductToShopify;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\ExportController;
use App\Models\Sale;

Route::get('/', function () {
    return view('welcome');
});

Route::post('api/webhooks/shopify', [ShopifyWebhookController::class, 'handle'])->withoutMiddleware(VerifyCsrfToken::class);;

Route::middleware(['auth'])->group(function () {
    Route::get('/export/products.csv', [ExportController::class, 'productsCsv'])->name('export.products.csv');
});


Route::get('/debug/session/set', fn() => tap(session(['ping' => now()->toDateTimeString()]), fn() => print 'set'));
Route::get('/debug/session/get', fn() => 'ping=' . session('ping'));

Route::post('/midtrans/callback', function (\Illuminate\Http\Request $r) {
    // verifikasi signature (tambahkan kalau perlu)
    $orderId = $r->input('order_id');
    $status = $r->input('transaction_status');
    $sale = Sale::where('invoice_no', $orderId)->first();
    if ($sale) {
        if (in_array($status, ['capture', 'settlement'])) {
            $sale->payment_status = 'paid';
            $sale->paid_at = now();
            $sale->saveQuietly();

            // kirim nota WA
            try {
                $url = app(\App\Services\ReceiptService::class)->generatePdf($sale);
                if ($sale->customer_phone) {
                    app(\App\Services\WhatsAppService::class)->sendText($sale->customer_phone, "Pembayaran berhasil. Invoice #{$sale->invoice_no}: {$url}");
                }
            } catch (\Throwable $e) {
            }
        }
    }
    return response()->json(['ok' => true]);
});
