<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAIClient
{
    private const RESPONSES_URL = 'https://api.openai.com/v1/responses';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $openAiApiKey,
        private readonly string $openAiModel = 'gpt-5.4-mini',
        private readonly int $timeoutSeconds = 20,
    ) {
    }

    /**
     * @return array{
     *     intent:string,
     *     confidence:float,
     *     entities:array<string, string|null>
     * }
     */
    public function classifyWhatsAppIntent(string $message): array
    {
        if (trim($message) === '' || trim($this->openAiApiKey) === '') {
            return $this->fallbackResult();
        }

        try {
            $response = $this->httpClient->request('POST', self::RESPONSES_URL, [
                'timeout' => $this->timeoutSeconds,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->openAiModel,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt(),
                        ],
                        [
                            'role' => 'user',
                            'content' => $message,
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'whatsapp_intent',
                            'strict' => true,
                            'schema' => $this->schema(),
                        ],
                    ],
                    'max_output_tokens' => 300,
                ],
            ]);

            $payload = $response->toArray(false);

            $text = $this->extractOutputText($payload);

            if ($text === null || trim($text) === '') {
                return $this->fallbackResult();
            }

            $decoded = json_decode($text, true);

            if (!is_array($decoded)) {
                return $this->fallbackResult();
            }

            return $this->normalizeResult($decoded);
        } catch (\Throwable $exception) {
            $this->logger->error('Error consultando OpenAI para clasificar intención WhatsApp.', [
                'detail' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->fallbackResult();
        }
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
Eres un clasificador de intención para un bot de WhatsApp de una barbería.

Tu tarea es clasificar el mensaje del cliente y extraer datos útiles.
No inventes servicios, barberos, sucursales, horarios ni folios.
No confirmes citas.
No canceles citas.
Solo devuelve JSON válido siguiendo el schema.

Intenciones permitidas:
- greeting
- book_appointment
- cancel_appointment
- reschedule_appointment
- list_services
- check_barbers
- product_recommendation
- haircut_recommendation
- unknown

Reglas:
- Si el cliente quiere agendar, reservar o consultar disponibilidad: book_appointment.
- Si el cliente quiere cancelar una cita o menciona cancelar folio: cancel_appointment.
- Si el cliente quiere mover, cambiar o reagendar una cita existente: reschedule_appointment.
- Si pregunta precios, servicios, corte, barba, fade, paquetes: list_services.
- Si pregunta por barberos, estilistas o quién atiende: check_barbers.
- Si pide productos para barba, cabello, pomada, gel, cera, frizz: product_recommendation.
- Si pide recomendación de corte por tipo de cara: haircut_recommendation.
- Si solo saluda o pide menú: greeting.
- Si no es claro: unknown.

Fechas:
- Mantén fechas relativas como "hoy", "mañana", "sábado", "viernes" tal cual en date_text.
- No conviertas fechas relativas a calendario.

Devuelve confidence entre 0 y 1.
PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'intent' => [
                    'type' => 'string',
                    'enum' => [
                        'greeting',
                        'book_appointment',
                        'cancel_appointment',
                        'reschedule_appointment',
                        'list_services',
                        'check_barbers',
                        'product_recommendation',
                        'haircut_recommendation',
                        'unknown',
                    ],
                ],
                'confidence' => [
                    'type' => 'number',
                ],
                'entities' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'folio' => ['type' => ['string', 'null']],
                        'date_text' => ['type' => ['string', 'null']],
                        'time_text' => ['type' => ['string', 'null']],
                        'branch' => ['type' => ['string', 'null']],
                        'service' => ['type' => ['string', 'null']],
                        'barber' => ['type' => ['string', 'null']],
                        'customer_name' => ['type' => ['string', 'null']],
                        'phone' => ['type' => ['string', 'null']],
                        'email' => ['type' => ['string', 'null']],
                        'face_shape' => ['type' => ['string', 'null']],
                        'product_need' => ['type' => ['string', 'null']],
                    ],
                    'required' => [
                        'folio',
                        'date_text',
                        'time_text',
                        'branch',
                        'service',
                        'barber',
                        'customer_name',
                        'phone',
                        'email',
                        'face_shape',
                        'product_need',
                    ],
                ],
            ],
            'required' => [
                'intent',
                'confidence',
                'entities',
            ],
        ];
    }

    private function extractOutputText(array $payload): ?string
    {
        if (isset($payload['output_text']) && is_string($payload['output_text'])) {
            return $payload['output_text'];
        }

        foreach (($payload['output'] ?? []) as $outputItem) {
            foreach (($outputItem['content'] ?? []) as $contentItem) {
                if (($contentItem['type'] ?? null) === 'output_text' && isset($contentItem['text'])) {
                    return (string) $contentItem['text'];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $decoded
     *
     * @return array{
     *     intent:string,
     *     confidence:float,
     *     entities:array<string, string|null>
     * }
     */
    private function normalizeResult(array $decoded): array
    {
        $allowedIntents = [
            'greeting',
            'book_appointment',
            'cancel_appointment',
            'reschedule_appointment',
            'list_services',
            'check_barbers',
            'product_recommendation',
            'haircut_recommendation',
            'unknown',
        ];

        $intent = (string) ($decoded['intent'] ?? 'unknown');

        if (!in_array($intent, $allowedIntents, true)) {
            $intent = 'unknown';
        }

        $confidence = (float) ($decoded['confidence'] ?? 0);

        if ($confidence < 0) {
            $confidence = 0;
        }

        if ($confidence > 1) {
            $confidence = 1;
        }

        $entities = is_array($decoded['entities'] ?? null)
            ? $decoded['entities']
            : [];

        $normalizedEntities = [];

        foreach ([
            'folio',
            'date_text',
            'time_text',
            'branch',
            'service',
            'barber',
            'customer_name',
            'phone',
            'email',
            'face_shape',
            'product_need',
        ] as $key) {
            $value = $entities[$key] ?? null;
            $normalizedEntities[$key] = is_string($value) && trim($value) !== ''
                ? trim($value)
                : null;
        }

        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'entities' => $normalizedEntities,
        ];
    }

    /**
     * @return array{
     *     intent:string,
     *     confidence:float,
     *     entities:array<string, string|null>
     * }
     */
    private function fallbackResult(): array
    {
        return [
            'intent' => 'unknown',
            'confidence' => 0.0,
            'entities' => [
                'folio' => null,
                'date_text' => null,
                'time_text' => null,
                'branch' => null,
                'service' => null,
                'barber' => null,
                'customer_name' => null,
                'phone' => null,
                'email' => null,
                'face_shape' => null,
                'product_need' => null,
            ],
        ];
    }
}
