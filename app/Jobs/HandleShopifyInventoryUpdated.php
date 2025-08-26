<?php

namespace App\Jobs;

use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleShopifyInventoryUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $inventoryItemId, public int $available) {}

    public function handle(): void
    {
        // Update stok varian lokal berdasarkan inventory_item_id
        ProductVariant::where('shopify_inventory_item_id', $this->inventoryItemId)
            ->update(['inventory_quantity' => $this->available]);
    }
}
