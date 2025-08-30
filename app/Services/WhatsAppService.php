<?php

namespace App\Services;

use GuzzleHttp\Client;

class WhatsAppService
{
    protected Client $http;
    protected string $token;
    protected string $phoneId;

    public function __construct()
    {
        $this->token  = (string) config('services.whatsapp.token'); // permanent token
        $this->phoneId = (string) config('services.whatsapp.phone_number_id');

        $this->http = new Client([
            'base_uri' => 'https://graph.facebook.com/v20.0/',
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    public function sendText(string $toE164, string $text): void
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toE164, // e.g. 62812xxxx
            'type' => 'text',
            'text' => ['body' => $text],
        ];
        $resp = $this->http->post("{$this->phoneId}/messages", ['json' => $payload]);
        $code = $resp->getStatusCode();
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('WA send failed: ' . (string)$resp->getBody());
        }
    }
}
