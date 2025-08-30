<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopProducts extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Produk Terlaris (Top 10)';

    /**
     * Kita agregasi penjualan per PRODUK:
     * sale_items (qty, line_total) -> join product_variants -> join products
     * Group by products.id, products.title
     */
    protected function getTableQuery(): Builder
    {
        return \App\Models\ProductVariant::query()
            ->join('sale_items', 'sale_items.product_variant_id', '=', 'product_variants.id')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->select([
                'product_variants.id',
                DB::raw("COALESCE(product_variants.title, products.title, 'Tanpa Nama') AS product_name"),
                DB::raw('SUM(sale_items.qty) AS qty'),
                DB::raw('SUM(sale_items.line_total) AS total'),
            ])
            ->groupBy('product_variants.id', 'product_name')
            ->orderByDesc('qty')
            ->limit(10);
    }


    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('product_name')
                ->label('Produk')
                ->searchable(),
            Tables\Columns\TextColumn::make('qty')
                ->label('Qty')
                ->sortable(),
            Tables\Columns\TextColumn::make('total')
                ->label('Total')
                ->money('IDR')
                ->sortable(),
        ];
    }
}
