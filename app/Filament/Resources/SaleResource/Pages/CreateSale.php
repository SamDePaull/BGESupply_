<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // subtotal tiap item & total transaksi
        $items = $data['items'] ?? [];
        $total = 0;

        foreach ($items as &$it) {
            $qty = (int)($it['quantity'] ?? 0);
            $price = $it['price'] ?? null;
            if ($price === null && !empty($it['product_id'])) {
                $price = (float) (UnifiedProduct::find($it['product_id'])->price ?? 0);
                $it['price'] = $price;
            }
            $it['subtotal'] = $qty * (float)$it['price'];
            $total += $it['subtotal'];
            }
        $data['items'] = $items;
        $data['total'] = $total;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Kurangi stok unified & opsional push stok ke Shopify (jika ada shopify_product_id)
        DB::transaction(function () {
            foreach ($this->record->items as $item) {
                $product = $item->product;
                if (!$product) continue;

                $newStock = max(0, (int)$product->stock - (int)$item->quantity);
                $product->update(['stock' => $newStock, 'sync_status' => 'synced']);

                // opsional push stok ke Shopify jika terhubung:
                if ($product->shopify_product_id) {
                    app(\App\Services\ShopifyInventoryService::class)
                        ->syncVariantInventoryBySku($product->sku, $newStock);
                }
            }
        });

        Notification::make()->title('Transaksi disimpan & stok diperbarui')->success()->send();
    }
}
