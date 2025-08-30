<?php

namespace App\Jobs;

use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleShopifyProductUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $shopifyProductId) {}
    public function handle(ShopifyService $svc): void
    {
        $svc->pullSingleProductAndIngest($this->shopifyProductId);
    }
}
