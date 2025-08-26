<?php

namespace App\Http\Controllers;

use App\Jobs\HandleShopifyInventoryUpdated;
use App\Jobs\HandleShopifyProductUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) {
            return response('Webhook secret missing', 500);
        }

        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $topic      = $request->header('X-Shopify-Topic');
        $domain     = $request->header('X-Shopify-Shop-Domain');

        $raw = $request->getContent();
        $calculated = base64_encode(hash_hmac('sha256', $raw, $secret, true));
        if (!hash_equals($calculated, (string)$hmacHeader)) {
            Log::warning('[ShopifyWebhook] HMAC mismatch', ['topic' => $topic, 'domain' => $domain]);
            return response('Invalid HMAC', 401);
        }

        $payload = json_decode($raw, true) ?? [];

        // simpan log mentah (opsional; kita sudah punya tabel shopify_webhooks)
        try {
            DB::table('shopify_webhooks')->insert([
                'event_type' => (string)$topic,
                'shopify_id' => isset($payload['id']) ? (int)$payload['id'] : null,
                'payload'    => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ShopifyWebhook] failed to store raw: ' . $e->getMessage());
        }

        // route ke job sesuai topik
        switch ($topic) {
            case 'products/create':
            case 'products/update':
                // payload bisa product penuh; tapi kita konsisten panggil pull by id
                $productId = $payload['id'] ?? null;
                if ($productId) {
                    dispatch(new HandleShopifyProductUpdated((int)$productId))->onQueue('shopify');
                }
                break;

            case 'products/delete':
                // tandai produk lokal sebagai dihapus (kalau di-maintain)
                // TODO opsional: soft delete local / lepaskan mapping
                break;

            case 'inventory_levels/update':
                // {inventory_item_id, location_id, available}
                $invItemId = $payload['inventory_item_id'] ?? null;
                $available = $payload['available'] ?? null;
                if ($invItemId !== null && $available !== null) {
                    dispatch(new HandleShopifyInventoryUpdated((int)$invItemId, (int)$available))->onQueue('shopify');
                }
                break;

            default:
                // biarkan tersimpan di shopify_webhooks untuk audit
                break;
        }

        return response('OK', 200);
    }
}
