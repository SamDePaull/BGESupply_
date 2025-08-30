<?php

namespace App\Http\Controllers;

use App\Jobs\HandleShopifyInventoryUpdated;
use App\Jobs\HandleShopifyProductUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('services.shopify.webhook_secret');
        if (!$secret) return response('Webhook secret missing', 500);
        $raw = $request->getContent();
        $hmacHeader = (string)$request->header('X-Shopify-Hmac-Sha256');
        $calc = base64_encode(hash_hmac('sha256', $raw, $secret, true));
        if (!hash_equals($calc, $hmacHeader)) {
            Log::warning('[ShopifyWebhook] HMAC mismatch');
            return response('Invalid HMAC', 401);
        }
        $topic = $request->header('X-Shopify-Topic');
        $payload = json_decode($raw, true) ?? [];
        try {
            DB::table('shopify_webhooks')->insert(['event_type' => (string)$topic, 'shopify_id' => isset($payload['id']) ? (int)$payload['id'] : null, 'payload' => json_encode($payload), 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('[ShopifyWebhook] store raw failed: ' . $e->getMessage());
        }
        switch ($topic) {
            case 'products/create':
            case 'products/update':
                if (isset($payload['id'])) dispatch(new HandleShopifyProductUpdated((int)$payload['id']))->onQueue('shopify');
                break;
            case 'inventory_levels/update':
                if (isset($payload['inventory_item_id'], $payload['available'])) dispatch(new HandleShopifyInventoryUpdated((int)$payload['inventory_item_id'], (int)$payload['available']))->onQueue('shopify');
                break;
            default:
                break;
        }
        return response('OK', 200);
    }
}
