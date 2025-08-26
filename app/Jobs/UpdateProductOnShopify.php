<?php

namespace App\Jobs;

use App\Exceptions\ShopifyHttpException;
use App\Models\Product;
use App\Models\SyncLog;
use App\Services\ShopifyPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UpdateProductOnShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 180, 600];

    public function __construct(public int $productId) {}

    public function handle(ShopifyPushService $svc): void
    {
        $product = Product::find($this->productId);
        if (!$product) return;

        try {
            $svc->updateUnifiedToShopify($product);

            $product->sync_status = 'synced';
            $product->last_error = null;
            $product->last_synced_at = now();
            $product->saveQuietly();
        } catch (ShopifyHttpException $e) {
            $product->sync_status = 'failed';
            $product->last_error = substr($e->getMessage(), 0, 2000);
            $product->saveQuietly();

            SyncLog::create([
                'job' => 'UpdateProductOnShopify',
                'action' => 'update',
                'product_id' => $product->id,
                'shopify_product_id' => $product->shopify_product_id,
                'http_status' => $e->status ?? null,
                'status' => 'failed',
                'message' => $e->getMessage(),
                'context' => [
                    'body' => $e->body ? mb_substr($e->body, 0, 1000) : null,
                ],
            ]);

            $this->fail($e);
        } catch (Throwable $e) {
            $product->sync_status = 'failed';
            $product->last_error = substr($e->getMessage(), 0, 2000);
            $product->saveQuietly();

            SyncLog::create([
                'job' => 'UpdateProductOnShopify',
                'action' => 'update',
                'product_id' => $product->id,
                'shopify_product_id' => $product->shopify_product_id,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
