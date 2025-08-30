<?php // EditOfflineProductStaging
namespace App\Filament\Resources\OfflineProductStagingResource\Pages;

use App\Filament\Resources\OfflineProductStagingResource;
use Filament\Resources\Pages\EditRecord;

class EditOfflineProductStaging extends EditRecord
{
    protected static string $resource = OfflineProductStagingResource::class;

    protected function getRedirectUrl(): string
    {
        return OfflineProductStagingResource::getUrl('index');
    }
}
