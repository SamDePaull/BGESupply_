<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ShopifyPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateProductOnShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $productId) {}
    public function handle(ShopifyPushService $svc): void
    {
        $p = Product::with(['variants', 'images', 'category'])->find($this->productId);
        if (!$p) return;
        $p->applyVariantPriceFallback();
        $svc->updateOnShopify($p);
    }
}
