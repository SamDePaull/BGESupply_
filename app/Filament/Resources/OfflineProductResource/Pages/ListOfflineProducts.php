<?php

// namespace App\Filament\Resources\OfflineProductResource\Pages;

// use App\Filament\Resources\OfflineProductResource;
// use Filament\Actions;
// use Filament\Resources\Pages\ListRecords;

// class ListOfflineProducts extends ListRecords
// {
//     protected static string $resource = OfflineProductResource::class;


// }


namespace App\Filament\Resources\OfflineProductResource\Pages;

use App\Filament\Resources\OfflineProductResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListOfflineProducts extends ListRecords
{
    protected static string $resource = OfflineProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

