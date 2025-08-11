<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ShopifyPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushProductToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Product $product) {}

    public function handle(ShopifyPushService $service): void
    {
        $service->pushUnifiedToShopify($this->product);
    }
}
