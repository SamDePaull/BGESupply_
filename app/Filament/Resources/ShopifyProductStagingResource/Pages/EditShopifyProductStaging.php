<?php

namespace App\Filament\Resources\ShopifyProductStagingResource\Pages;

use App\Filament\Resources\ShopifyProductStagingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopifyProductStaging extends EditRecord
{
    protected static string $resource = ShopifyProductStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
