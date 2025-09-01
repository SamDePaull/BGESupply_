<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopifyProductStagingResource\Pages;
use App\Models\ShopifyProductStaging;
use App\Services\ProductMergeService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class ShopifyProductStagingResource extends Resource
{
    protected static ?string $model = ShopifyProductStaging::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static bool $shouldRegisterNavigation = false; // sembunyikan dari sidebar


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('shopify_product_id')->required()->numeric(),
            Forms\Components\TextInput::make('handle'),
            Forms\Components\TextInput::make('title'),
            Forms\Components\Textarea::make('payload')->required()->helperText('Paste JSON product dari Shopify'),
            Forms\Components\Select::make('status')->options([
                'pulled' => 'pulled',
                'ready' => 'ready',
                'merged' => 'merged',
                'failed' => 'failed'
            ])->default('pulled'),
            Forms\Components\Textarea::make('notes'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shopify_product_id')->label('Shopify ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('handle')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->label('Created')->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('merge')
                    ->label('Merge dari Shopify')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(ProductMergeService::class)->mergeShopify($record);
                        \Filament\Notifications\Notification::make()
                            ->title('Merged')
                            ->body('Data staging Shopify berhasil di-merge ke Product.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkMerge')
                        ->label('Bulk: Merge dari Shopify')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $svc = app(ProductMergeService::class);
                            foreach ($records as $rec) {
                                $svc->mergeShopify($rec);
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('Bulk merge selesai')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ])
            ]);
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopifyProductStagings::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
}
