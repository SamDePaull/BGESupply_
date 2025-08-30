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
    public function __construct(public int $productId, public int $shopifyProductId, public bool $deleteLocal = false) {}
    public function handle(ShopifyPushService $svc): void
    {
        $p = Product::find($this->productId);
        if (!$p) return;
        try {
            $svcJson = $this->deleteOnShopify($this->shopifyProductId);
            if ($this->deleteLocal) {
                $p->variants()->delete();
                $p->images()->delete();
                $p->delete();
            } else {
                $p->shopify_product_id = null;
                $p->sync_status = 'pending';
                $p->last_synced_at = now();
                $p->saveQuietly();
            }
            SyncLog::create(['job' => 'DeleteProductOnShopify', 'action' => 'delete', 'product_id' => $this->productId, 'shopify_product_id' => $this->shopifyProductId, 'status' => 'ok']);
        } catch (\Throwable $e) {
            SyncLog::create(['job' => 'DeleteProductOnShopify', 'action' => 'delete', 'product_id' => $this->productId, 'shopify_product_id' => $this->shopifyProductId, 'status' => 'failed', 'message' => $e->getMessage()]);
            $this->fail($e);
        }
    }
    protected function deleteOnShopify(int $id)
    {
        $svc = new \GuzzleHttp\Client(['base_uri' => "https://" . config('services.shopify.shop') . ".myshopify.com/admin/api/" . config('services.shopify.version', '2025-07') . "/", 'headers' => ['X-Shopify-Access-Token' => config('services.shopify.token'), 'Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        return (string)$svc->delete("products/{$id}.json")->getBody();
    }
}
