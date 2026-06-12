<?php

namespace App\Service\WhatsApp;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WhatsAppClient
{
    private const GRAPH_API_VERSION = 'v25.0';

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
                'to' => $this->normalizeRecipient($to),
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ],
        ]);

        return $response->toArray(false);
    }

    public function sendBookingConfirmationTemplate(
        string $to,
        string $customerName,
        string $appointmentDetails
    ): array {
        return $this->sendTemplateMessage(
            to: $to,
            templateName: 'booking_confirmation',
            languageCode: 'es_MX',
            bodyParameters: [
                $customerName,
                $appointmentDetails,
            ]
        );
    }

    public function sendTemplateMessage(
        string $to,
        string $templateName,
        string $languageCode,
        array $bodyParameters = []
    ): array {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            self::GRAPH_API_VERSION,
            $this->whatsappPhoneNumberId
        );

        $components = [];

        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string|int|float $value): array => [
                        'type' => 'text',
                        'text' => (string) $value,
                    ],
                    $bodyParameters
                ),
            ];
        }

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->whatsappAccessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->normalizeRecipient($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $languageCode,
                    ],
                    'components' => $components,
                ],
            ],
        ]);

        return $response->toArray(false);
    }


    public function markAsReadAndTyping(string $messageId): array
    {
        $messageId = trim($messageId);

        if ($messageId === '') {
            return [];
        }

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
                'status' => 'read',
                'message_id' => $messageId,
                'typing_indicator' => [
                    'type' => 'text',
                ],
            ],
        ]);

        return $response->toArray(false);
    }

    public function normalizeRecipient(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return $phone;
        }

        if (str_starts_with($digits, '521') && strlen($digits) === 13) {
            return $digits;
        }

        if (str_starts_with($digits, '52') && strlen($digits) === 12) {
            return '521' . substr($digits, 2);
        }

        if (strlen($digits) === 10) {
            return '521' . $digits;
        }

        return $digits;
    }
}
