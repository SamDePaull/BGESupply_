<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ShopifyVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'shopifyVariants'; // relasi di model Product

    public function table(Table $table): Table
    {
        return $table
            ->heading('Shopify Variants')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Variant ID'),
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('sku'),
                Tables\Columns\TextColumn::make('price')->money('IDR'),
                Tables\Columns\TextColumn::make('inventory_quantity')->label('Qty'),
                Tables\Columns\TextColumn::make('option1')->label('Opt1'),
                Tables\Columns\TextColumn::make('option2')->label('Opt2'),
                Tables\Columns\TextColumn::make('option3')->label('Opt3'),
                Tables\Columns\TextColumn::make('shopify_updated_at')->dateTime()->label('Updated'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
