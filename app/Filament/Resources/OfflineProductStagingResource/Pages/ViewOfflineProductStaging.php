<?php

namespace App\Filament\Resources\OfflineProductStagingResource\Pages;

use App\Filament\Resources\OfflineProductStagingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOfflineProductStaging extends ViewRecord
{
    protected static string $resource = OfflineProductStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
