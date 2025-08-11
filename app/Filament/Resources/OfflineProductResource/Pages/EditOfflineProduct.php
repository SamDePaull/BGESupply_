<?php

namespace App\Filament\Resources\OfflineProductResource\Pages;

// use App\Filament\Resources\OfflineProductResource;
// use Filament\Actions;
// use Filament\Resources\Pages\EditRecord;

// class EditOfflineProduct extends EditRecord
// {
//     protected static string $resource = OfflineProductResource::class;

//     protected function getHeaderActions(): array
//     {
//         return [
//             Actions\DeleteAction::make(),
//         ];
//     }
// }


namespace App\Filament\Resources\OfflineProductResource\Pages;

use App\Filament\Resources\OfflineProductResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Services\OfflineProductService;

class EditOfflineProduct extends EditRecord
{
    protected static string $resource = OfflineProductResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }


    protected function getRedirectUrl(): string
    {
        // setelah save, arahkan ke index (list)
        return static::getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        // pastikan langsung kembali ke list
        $this->redirect($this->getRedirectUrl());
    }
}

