<?php

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

Route::middleware('auth')->group(function () {
    Route::get('/sync-shopify-now', function (ShopifyService $svc) {
        $count = $svc->pullAndIngest();
        return "Synced {$count} products.";
    })->name('shopify.sync.now');

    Route::post('/queue-sync-shopify', function () {
        dispatch(new SyncShopifyProducts());
        return 'Queued.';
    });

    Route::post('/queue-push-product/{product}', function (Product $product) {
        dispatch(new PushProductToShopify($product));
        return 'Push queued.';
    });
});

Route::post('/webhooks/shopify', [ShopifyWebhookController::class, 'handle'])
    ->name('shopify.webhooks'); // set URL ini di admin Shopify Webhooks

Route::middleware(['auth'])->group(function () {
    Route::get('/export/products.csv', [ExportController::class, 'productsCsv'])->name('export.products.csv');
});
