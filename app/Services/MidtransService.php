<?php

namespace App\Services;

use GuzzleHttp\Client;

class MidtransService
{
    protected Client $http;
    protected string $serverKey;
    protected bool $isProduction;

    public function __construct()
    {
        $this->serverKey = config('services.midtrans.server_key');
        $this->isProduction = (bool)config('services.midtrans.production', false);
        $base = $this->isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';

        $this->http = new Client([
            'base_uri' => $base,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->serverKey . ':'),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'http_errors' => false,
            'timeout' => 60,
        ]);
    }

    public function createTransaction(array $payload): array
    {
        // payload minimal: transaction_details, customer_details, item_details
        $resp = $this->http->post('/v2/charge', ['json' => $payload]);
        $status = $resp->getStatusCode();
        $data = json_decode((string)$resp->getBody(), true) ?: [];
        if ($status >= 200 && $status < 300) return $data;

        throw new \RuntimeException('Midtrans charge failed: ' . json_encode($data));
    }
}
