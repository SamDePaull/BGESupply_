<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('pullAllFromShopify')
                ->label('Pull All from Shopify')
                ->icon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->action(function () {
                    dispatch(new \App\Jobs\PullShopifyProducts())->onQueue('shopify');
                })
                ->successNotificationTitle('Pull enqueued'),
        ];
    }
}
