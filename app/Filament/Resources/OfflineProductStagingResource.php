<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfflineProductStagingResource\Pages;
use App\Models\OfflineProductStaging;
use App\Services\ProductMergeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Table;

class OfflineProductStagingResource extends Resource
{
    protected static ?string $model = OfflineProductStaging::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static bool $shouldRegisterNavigation = false; // sembunyikan dari sidebar

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic')
                ->schema([
                    Forms\Components\TextInput::make('title')->required(),
                    Forms\Components\TextInput::make('handle'),
                    Forms\Components\TextInput::make('sku'),
                    Forms\Components\TextInput::make('barcode'),
                    Forms\Components\TextInput::make('vendor'),
                    Forms\Components\TextInput::make('product_type'),
                    Forms\Components\TextInput::make('price')->numeric(),
                    Forms\Components\TextInput::make('compare_at_price')->numeric(),
                    Forms\Components\TextInput::make('inventory_quantity')->numeric(),
                    Forms\Components\Textarea::make('notes'),
                ])->columns(3),
            Forms\Components\Section::make('Options / Variants / Images')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('options')->helperText('JSON: [{"name":"Size","values":["M","L"]}]'),
                    Forms\Components\Textarea::make('variants')->helperText('JSON: [{"sku":"A1","option1":"M","price":100,"inventory_quantity":5}]'),
                    Forms\Components\Textarea::make('images')->helperText('JSON: [{"path":"products/a.jpg","alt":"Photo"}]'),
                ]),
        ]);
    }

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('title')->label('Title')->searchable()->sortable()->limit(40),
            Tables\Columns\TextColumn::make('handle')->sortable()->toggleable(),
            Tables\Columns\TextColumn::make('sku')->toggleable(),
            Tables\Columns\TextColumn::make('price')->toggleable(),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->label('Created')->sortable(),
        ])
        ->actions([
            Tables\Actions\Action::make('merge')
                ->label('Merge ke Product')
                ->icon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->action(function ($record) {
                    app(ProductMergeService::class)->mergeOffline($record);
                    \Filament\Notifications\Notification::make()
                        ->title('Merged')
                        ->body('Data staging offline berhasil di-merge ke Product.')
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ])
        ->bulkActions([
            BulkActionGroup::make([
                BulkAction::make('bulkMerge')
                    ->label('Bulk: Merge ke Product')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $svc = app(ProductMergeService::class);
                        foreach ($records as $rec) {
                            $svc->mergeOffline($rec);
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


    // Hanya daftar; hilangkan Create/Edit dari menu
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfflineProductStagings::route('/'),
        ];
    }

    // Opsional (tambahan pagar pengaman agar tidak bisa create/edit via URL)
    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
}
