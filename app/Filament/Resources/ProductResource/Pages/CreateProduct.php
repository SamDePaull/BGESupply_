<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array<int, array{sku:?string,o1:?string,o2:?string,o3:?string,path:string}> */
    protected array $pendingVariantImages = [];

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingVariantImages = [];

        foreach (($data['variants'] ?? []) as $i => $row) {
            $path = $row['tmp_image'] ?? null;
            if ($path) {
                $this->pendingVariantImages[] = [
                    'sku' => $row['sku'] ?? null,
                    'o1'  => $row['option1_value'] ?? null,
                    'o2'  => $row['option2_value'] ?? null,
                    'o3'  => $row['option3_value'] ?? null,
                    'path' => $path,
                ];
                // jangan disimpan sebagai kolom varian
                unset($data['variants'][$i]['tmp_image']);
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $product = $this->record;

        DB::transaction(function () use ($product) {
            foreach ($this->pendingVariantImages as $pvi) {
                // buat ProductImage dulu
                $img = $product->images()->create([
                    'file_path' => $pvi['path'],
                    'alt'       => $product->title,
                ]);

                // temukan varian berdasarkan SKU (utama), kalau kosong pakai kombinasi opsi
                $variant = null;
                if (!empty($pvi['sku'])) {
                    $variant = $product->variants()->where('sku', $pvi['sku'])->first();
                }
                if (!$variant) {
                    $variant = $product->variants()
                        ->where('option1_value', $pvi['o1'])
                        ->where('option2_value', $pvi['o2'])
                        ->where('option3_value', $pvi['o3'])
                        ->first();
                }
                if ($variant) {
                    $variant->product_image_id = $img->id;
                    $variant->saveQuietly();
                }
            }
        });
    }
}
