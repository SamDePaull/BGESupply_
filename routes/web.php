<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use App\Services\ShopifyService;
use App\Jobs\SyncShopifyProducts;
use App\Models\Product;
use App\Jobs\PushProductToShopify;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\ExportController;



Route::get('/', function () {
    return view('welcome');
});

Route::post('api/webhooks/shopify', [ShopifyWebhookController::class, 'handle'])->withoutMiddleware(VerifyCsrfToken::class);;

Route::middleware(['auth'])->group(function () {
    Route::get('/export/products.csv', [ExportController::class, 'productsCsv'])->name('export.products.csv');
});


Route::get('/debug/session/set', fn() => tap(session(['ping'=>now()->toDateTimeString()]), fn() => print 'set'));
Route::get('/debug/session/get', fn() => 'ping='.session('ping'));

