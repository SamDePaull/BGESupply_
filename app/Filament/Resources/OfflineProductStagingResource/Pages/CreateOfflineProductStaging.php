<?php // CreateOfflineProductStaging
namespace App\Filament\Resources\OfflineProductStagingResource\Pages;

use App\Filament\Resources\OfflineProductStagingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOfflineProductStaging extends CreateRecord
{
    protected static string $resource = OfflineProductStagingResource::class;

    protected function getRedirectUrl(): string
    {
        return OfflineProductStagingResource::getUrl('index');
    }
}
