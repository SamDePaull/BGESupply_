<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ShopifyPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class PushProductToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 5;
    public $backoff = [60, 120, 300, 600, 1200];
    public function __construct(public int $productId) {}
    public function handle(ShopifyPushService $svc): void
    {
        $product = Product::find($this->productId);
        if (!$product) return;
        $svc->pushUnifiedToShopify($product);
        $product->sync_status = 'synced';
        $product->last_error = null;
        $product->last_synced_at = now();
        $product->saveQuietly();
    }
    public function failed(Throwable $e): void
    {
        if ($p = Product::find($this->productId)) {
            $p->sync_status = 'failed';
            $p->last_error = substr($e->getMessage(), 0, 2000);
            $p->saveQuietly();
        }
    }
}
