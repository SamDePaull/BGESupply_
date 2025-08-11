<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ShopifyImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'shopifyImages'; // relasi di model Product

    public function table(Table $table): Table
    {
        return $table
            ->heading('Shopify Images')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Image ID'),
                Tables\Columns\ImageColumn::make('src')->label('Image')->circular(),
                Tables\Columns\TextColumn::make('position'),
                Tables\Columns\TextColumn::make('width'),
                Tables\Columns\TextColumn::make('height'),
                Tables\Columns\TextColumn::make('alt'),
                Tables\Columns\TextColumn::make('shopify_updated_at')->dateTime()->label('Updated'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
