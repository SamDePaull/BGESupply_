<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class NotifyLowStock extends Command
{
    protected $signature = 'inventory:notify-low-stock';
    protected $description = 'Log or email list of low stock products';

    public function handle(): int
    {
        $threshold = (int) config('inventory.low_stock_threshold', 5);
        $items = Product::where('stock', '<=', $threshold)->orderBy('stock')->get(['id','name','sku','stock']);

        if ($items->isEmpty()) {
            $this->info('No low stock items.');
            return self::SUCCESS;
        }

        // di sini bisa kirim email; sementara kita log saja
        Log::info('Low stock report', ['items' => $items->toArray()]);
        $this->info("Low stock items: " . $items->count());

        return self::SUCCESS;
    }
}
