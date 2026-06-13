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
        /*
         * WhatsAppClient ya no decide país ni agrega prefijos.
         *
         * El número debe llegar ya normalizado desde PhoneNumberService:
         * - México: 5218180201499
         * - Cuba: 5355848425
         * - Colombia: 573001234567
         *
         * Aquí solo limpiamos espacios, paréntesis, guiones y el signo +.
         */
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return $phone;
        }

        return $digits;
    }
}
