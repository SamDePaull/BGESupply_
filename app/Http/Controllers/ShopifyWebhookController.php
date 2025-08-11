<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verifikasi HMAC
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $data = $request->getContent();
        $hmac = base64_encode(hash_hmac('sha256', $data, env('SHOPIFY_API_SECRET'), true));
        if (!hash_equals($hmac, $hmacHeader)) {
            Log::warning('Invalid Shopify HMAC');
            return response('Invalid HMAC', 401);
        }

        $topic = $request->header('X-Shopify-Topic'); // orders/create, products/update, etc.
        $payload = $request->json()->all();

        switch ($topic) {
            case 'orders/create':
                app(\App\Services\ShopifyWebhookService::class)->handleOrderCreate($payload);
                break;
            case 'products/update':
                app(\App\Services\ShopifyWebhookService::class)->handleProductUpdate($payload);
                break;
            default:
                Log::info('Unhandled Shopify webhook', ['topic' => $topic]);
        }

        return response('OK', 200);
    }
}

