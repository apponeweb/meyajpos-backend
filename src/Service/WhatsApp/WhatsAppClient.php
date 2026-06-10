<?php

namespace App\Service\WhatsApp;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WhatsAppClient
{
    private const GRAPH_API_VERSION = 'v20.0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $whatsappAccessToken,
        private readonly string $whatsappPhoneNumberId
    ) {
    }

    public function sendTextMessage(string $to, string $message): array
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            self::GRAPH_API_VERSION,
            $this->whatsappPhoneNumberId
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->whatsappAccessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ],
        ]);

        return $response->toArray(false);
    }
}