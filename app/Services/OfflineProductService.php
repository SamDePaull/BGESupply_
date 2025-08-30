<?php

namespace App\Services;

use App\Models\Product;
use App\Models\OfflineProductStaging;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

// alias supaya rule unique ke tabel unified jelas
use App\Models\Product as UnifiedProduct;

class OfflineProductService
{
    /**
     * Validasi input produk offline + SKU unik lintas offline_products & products.
     * NOTE: untuk update, sebaiknya pakai FormRequest yang meng-IGNORE record terkait.
     */
    protected function validatePayload(array $data): array
    {
        $v = Validator::make($data, [
            'name'       => ['required', 'string'],
            'sku'        => [
                'required', 'string',
                Rule::unique('offline_products', 'sku'),
                Rule::unique((new UnifiedProduct)->getTable(), 'sku'),
            ],
            'price'      => ['required', 'numeric'],
            'stock'      => ['required', 'integer'],
            'cost_price' => ['nullable', 'numeric'],
            'image_url'  => ['nullable', 'url'],
            'attributes' => ['nullable', 'array'],
        ]);

        $v->validate();
        return $v->validated();
    }

    /**
     * Buat produk offline → duplikasi ke products (unified) dengan origin='offline'.
     * origin_id akan menunjuk ke offline_products.id (bukan ID Shopify).
     */
    public function createOfflineAndDuplicate(array $data): Product
    {
        $this->validatePayload($data);

        return DB::transaction(function () use ($data) {
            // 1) Simpan ke tabel sumber offline
            $offline = OfflineProductStaging::create([
                'name'       => $data['name'],
                'sku'        => $data['sku'],
                'price'      => (float) $data['price'],
                'cost_price' => $data['cost_price'] ?? null,
                'stock'      => (int) $data['stock'],
                'image_url'  => $data['image_url'] ?? null,
                'attributes' => $data['attributes'] ?? null,
            ]);

            // 2) Duplikasi ke unified (tanpa sentuh Shopify)
            $unified = Product::updateOrCreate(
                ['origin' => 'offline', 'origin_id' => $offline->id],
                [
                    'name'            => $offline->name,
                    'sku'             => $offline->sku,
                    'price'           => $offline->price,
                    'cost_price'      => $offline->cost_price,
                    'stock'           => $offline->stock,
                    'image_url'       => $offline->image_url,
                    'is_from_shopify' => false,
                    'sync_status'     => 'pending',
                    // JANGAN isi shopify_product_id di sini
                ]
            );

            return $unified;
        });
    }

    /**
     * Buat produk offline → duplikasi → otomatis push ke Shopify.
     * TIDAK mengubah origin/origin_id unified; hanya mengisi shopify_product_id & sync_status.
     */
    public function createAndPushToShopify(array $data): Product
    {
        $product = $this->createOfflineAndDuplicate($data);

        // Push ke Shopify
        $pushed = app(ShopifyPushService::class)->pushUnifiedToShopify($product);

        if (!$pushed) {
            Log::warning('Push to Shopify failed for offline product', ['product_id' => $product->id]);
        }

        return $product->fresh();
    }

    /**
     * Sinkronkan produk offline yang SUDAH ada (by offline_products.id)
     * → pastikan unified ada → push ke Shopify kalau belum terbit.
     */
    public function syncExistingOfflineToShopify(int $offlineId): bool
    {
        $offline = OfflineProductStaging::find($offlineId);
        if (!$offline) {
            Log::warning('Offline product not found', ['offline_id' => $offlineId]);
            return false;
        }

        return DB::transaction(function () use ($offline) {
            // Pastikan unified ada
            $unified = Product::firstOrCreate(
                ['origin' => 'offline', 'origin_id' => $offline->id],
                [
                    'name'            => $offline->name,
                    'sku'             => $offline->sku,
                    'price'           => $offline->price,
                    'cost_price'      => $offline->cost_price,
                    'stock'           => $offline->stock,
                    'image_url'       => $offline->image_url,
                    'is_from_shopify' => false,
                    'sync_status'     => 'pending',
                ]
            );

            // Jika sudah punya shopify_product_id, anggap sudah sinkron
            if ($unified->shopify_product_id) {
                return true;
            }

            // Push ke Shopify
            return app(ShopifyPushService::class)->pushUnifiedToShopify($unified);
        });
    }
}
