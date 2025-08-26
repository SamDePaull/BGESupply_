<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SyncLog;
use App\Services\ShopifyPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteProductOnShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 3;
    public $backoff = [60, 180, 600];
    public function __construct(
        public int $productId,
        public int $shopifyProductId,
        public bool $deleteLocal = false,
    ) {}
    public function handle(ShopifyPushService $svc): void
    {
        $product = Product::find($this->productId);
        if (!$product) return;
        try {
            $svc->deleteOnShopify($this->shopifyProductId, $product);
            if ($this->deleteLocal) {
                // hapus varian dulu supaya rapi
                $product->variants()->delete();
                $product->delete();
            } else {
                // pertahankan lokal, tapi kosongkan mapping shopify
                $product->shopify_product_id = null;
                $product->sync_status = 'pending';
                $product->last_synced_at = now();
                $product->saveQuietly();
            }
            SyncLog::create([
                'job' => 'DeleteProductOnShopify',
                'action' => 'delete',
                'product_id' => $this->productId,
                'shopify_product_id' => $this->shopifyProductId,
                'status' => 'ok',
                'message' => $this->deleteLocal ? 'deleted shopify + local' :
                    'deleted on shopify only',
            ]);
        } catch (\Throwable $e) {
            SyncLog::create([
                'job' => 'DeleteProductOnShopify',
                'action' => 'delete',
                'product_id' => $this->productId,
                'shopify_product_id' => $this->shopifyProductId,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }
}
