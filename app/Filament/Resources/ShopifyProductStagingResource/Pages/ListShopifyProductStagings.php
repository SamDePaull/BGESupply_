<?php

namespace App\Filament\Resources\ShopifyProductStagingResource\Pages;

use App\Filament\Resources\ShopifyProductStagingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShopifyProductStagings extends ListRecords
{
    protected static string $resource = ShopifyProductStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
