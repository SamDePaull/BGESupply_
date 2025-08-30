<?php

namespace App\Filament\Resources\ShopifyProductStagingResource\Pages;

use App\Filament\Resources\ShopifyProductStagingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShopifyProductStaging extends ViewRecord
{
    protected static string $resource = ShopifyProductStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
