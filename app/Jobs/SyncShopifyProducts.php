<?php

namespace App\Jobs;

use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncShopifyProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ShopifyService $service): void
    {
        \Log::info('SyncShopifyProducts started');
        $service->pullAndIngest();
        \Log::info('SyncShopifyProducts finished');
    }
}
