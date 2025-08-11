<?php

namespace App\Filament\Resources\OfflineProductResource\Pages;

use App\Filament\Resources\OfflineProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Services\OfflineProductService;

class CreateOfflineProduct extends CreateRecord
{
    protected static string $resource = OfflineProductResource::class;

    protected function handleRecordCreation(array $data): \App\Models\OfflineProduct
    {
        // Simpan record offline (default behaviour)
        /** @var \App\Models\OfflineProduct $record */
        $record = static::getModel()::create($this->mutateFormDataBeforeCreate($data));

        // AUTO PUSH: duplikasi → push ke Shopify (paksa true)
        app(OfflineProductService::class)->createAndPushToShopify([
            'name'       => $record->name,
            'sku'        => $record->sku,
            'price'      => $record->price,
            'cost_price' => $record->cost_price,
            'stock'      => $record->stock,
            'image_url'  => $record->image_url,
            'attributes' => $record->attributes,
        ]);

        Notification::make()->title('Produk dibuat & dipush ke Shopify')->success()->send();

        return $record;
    }
}
