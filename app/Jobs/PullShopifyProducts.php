<?php

namespace App\Jobs;

use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullShopifyProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = [60, 180, 600];

    public function handle(ShopifyService $svc): void
    {
        try {
            $count = $svc->pullAndIngest();
            Log::info('[PullShopifyProducts] OK pulled=' . $count);
        } catch (\Throwable $e) {
            Log::error('[PullShopifyProducts] FAIL: ' . $e->getMessage(), [
                'trace' => collect($e->getTrace())->take(5)->all(),
            ]);
            $this->fail($e); // tandai FAILED supaya kelihatan di queue:failed
        }
    }
}
