<?php

namespace App\Service\WhatsApp;

use App\Service\AI\OpenAIClient;
use Psr\Log\LoggerInterface;

final class IntentClassifier
{
    public function __construct(
        private readonly OpenAIClient $openAIClient,
        private readonly LoggerInterface $logger,
        private readonly bool $openAiEnabled = true,
        private readonly float $minimumAiConfidence = 0.70,
    ) {
    }

    public function detect(string $message): string
    {
        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return 'unknown';
        }

        $ruleIntent = $this->detectByRules($text);

        if ($ruleIntent !== 'unknown') {
            return $ruleIntent;
        }

        if (!$this->openAiEnabled) {
            return 'unknown';
        }

        $aiResult = $this->openAIClient->classifyWhatsAppIntent($message);
        $aiIntent = (string) ($aiResult['intent'] ?? 'unknown');
        $confidence = (float) ($aiResult['confidence'] ?? 0);

        $this->logger->info('OpenAI intent classifier result', [
            'message' => $message,
            'intent' => $aiIntent,
            'confidence' => $confidence,
            'entities' => $aiResult['entities'] ?? [],
        ]);

        if ($confidence < $this->minimumAiConfidence) {
            return 'unknown';
        }

        return match ($aiIntent) {
            'greeting' => 'greeting',
            'book_appointment' => 'check_agenda',
            'cancel_appointment' => 'cancel_appointment',
            'reschedule_appointment' => 'reschedule',
            'list_services' => 'list_services',
            'check_barbers' => 'check_barbers',
            'product_recommendation' => 'product_recommendation',
            'haircut_recommendation' => 'haircut_recommendation',
            default => 'unknown',
        };
    }

    /**
     * @return array{
     *     intent:string,
     *     confidence:float,
     *     entities:array<string, string|null>
     * }
     */
    public function analyze(string $message): array
    {
        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return $this->fallbackAnalysis();
        }

        $ruleIntent = $this->detectByRules($text);

        if ($ruleIntent !== 'unknown') {
            return [
                'intent' => $this->mapLegacyIntentToAiIntent($ruleIntent),
                'confidence' => 1.0,
                'entities' => $this->extractEntitiesByRules($text),
            ];
        }

        if (!$this->openAiEnabled) {
            return $this->fallbackAnalysis();
        }

        return $this->openAIClient->classifyWhatsAppIntent($message);
    }

    private function detectByRules(string $text): string
    {
        if ($this->containsAny($text, [
            'cancelar cita',
            'cancelar mi cita',
            'cancelacion',
            'cancelación',
            'cancelar folio',
        ])) {
            return 'cancel_appointment';
        }

        if ($this->containsAny($text, [
            'reagendar',
            'cambiar cita',
            'mover cita',
            'cambiar mi cita',
            'modificar cita',
            'reprogramar',
        ])) {
            return 'reschedule';
        }

        if ($this->containsAny($text, [
            'barbero',
            'barberos',
            'quien corta',
            'quién corta',
            'profesional',
            'estilista',
            'quien atiende',
            'quién atiende',
        ])) {
            return 'check_barbers';
        }

        if ($this->containsAny($text, [
            'agenda',
            'cita',
            'horario',
            'horarios',
            'disponible',
            'disponibilidad',
            'reservar',
            'agendar',
            'hoy',
            'mañana',
            'manana',
        ])) {
            return 'check_agenda';
        }

        if ($this->containsAny($text, [
            'servicio',
            'servicios',
            'precio',
            'precios',
            'cuanto cuesta',
            'cuánto cuesta',
            'corte y barba',
            'barba',
            'fade',
            'desvanecido',
        ])) {
            return 'list_services';
        }

        if ($this->containsAny($text, [
            'producto',
            'productos',
            'pomada',
            'cera',
            'gel',
            'shampoo',
            'aceite',
            'fijacion',
            'fijación',
            'frizz',
            'peinar',
        ])) {
            return 'product_recommendation';
        }

        if ($this->containsAny($text, [
            'corte me queda',
            'tipo de cara',
            'cara redonda',
            'cara ovalada',
            'cara cuadrada',
            'cara alargada',
            'cara diamante',
            'cara corazón',
            'cara corazon',
            'recomienda un corte',
            'recomendar corte',
            'corte para cara',
            'que corte',
            'qué corte',
        ])) {
            return 'haircut_recommendation';
        }

        if ($this->containsAny($text, [
            'hola',
            'buenas',
            'buen día',
            'buen dia',
            'hey',
            'menu',
            'menú',
            'inicio',
        ])) {
            return 'greeting';
        }

        return 'unknown';
    }

    private function mapLegacyIntentToAiIntent(string $intent): string
    {
        return match ($intent) {
            'greeting' => 'greeting',
            'check_agenda' => 'book_appointment',
            'cancel_appointment' => 'cancel_appointment',
            'reschedule' => 'reschedule_appointment',
            'list_services' => 'list_services',
            'check_barbers' => 'check_barbers',
            'product_recommendation' => 'product_recommendation',
            'haircut_recommendation' => 'haircut_recommendation',
            default => 'unknown',
        };
    }

    /**
     * @return array<string, string|null>
     */
    private function extractEntitiesByRules(string $text): array
    {
        $entities = $this->emptyEntities();

        if (preg_match('/(?:folio|cita)\s*#?\s*(\d+)/i', $text, $matches)) {
            $entities['folio'] = $matches[1];
        } elseif (preg_match('/^\d+$/', $text, $matches)) {
            $entities['folio'] = $matches[0];
        }

        foreach (['hoy', 'mañana', 'manana'] as $dateWord) {
            if (str_contains($text, $dateWord)) {
                $entities['date_text'] = $dateWord;
                break;
            }
        }

        if (preg_match('/\b(\d{1,2}\/\d{1,2}\/\d{4})\b/', $text, $matches)) {
            $entities['date_text'] = $matches[1];
        }

        if (preg_match('/\b(\d{1,2}:\d{2}\s*(?:am|pm)?)\b/i', $text, $matches)) {
            $entities['time_text'] = $matches[1];
        }

        foreach (['redonda', 'ovalada', 'cuadrada', 'alargada', 'diamante', 'corazon', 'corazón'] as $shape) {
            if (str_contains($text, $shape)) {
                $entities['face_shape'] = $shape;
                break;
            }
        }

        return $entities;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     intent:string,
     *     confidence:float,
     *     entities:array<string, string|null>
     * }
     */
    private function fallbackAnalysis(): array
    {
        return [
            'intent' => 'unknown',
            'confidence' => 0.0,
            'entities' => $this->emptyEntities(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function emptyEntities(): array
    {
        return [
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
        ];
    }
}
