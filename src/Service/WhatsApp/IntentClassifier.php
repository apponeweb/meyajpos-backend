<?php

namespace App\Service\WhatsApp;

use App\Service\AI\OpenAIClient;
use Psr\Log\LoggerInterface;

final class IntentClassifier
{
    private const BOOKING_TIMEZONE = 'America/Mexico_City';

    public function __construct(
        private readonly OpenAIClient $openAIClient,
        private readonly LoggerInterface $logger,
        private readonly bool $openAiEnabled = true,
        private readonly float $minimumAiConfidence = 0.70,
    ) {
    }

    public function detect(string $message): string
    {
        return $this->mapAiIntentToLegacyIntent((string) $this->analyze($message)['intent']);
    }

    /**
     * @return array{
     *     intent:string,
     *     confidence:float,
     *     source:string,
     *     entities:array<string, string|null>
     * }
     */
    public function analyze(string $message): array
    {
        $text = $this->normalizeText($message);

        if ($text === '') {
            return $this->fallbackAnalysis();
        }

        $entities = $this->extractEntitiesByRules($message);
        $ruleIntent = $this->detectByRules($text, $entities);

        if ($ruleIntent !== 'unknown') {
            return [
                'intent' => $this->mapLegacyIntentToAiIntent($ruleIntent),
                'confidence' => 1.0,
                'source' => 'rules',
                'entities' => $entities,
            ];
        }

        if (!$this->openAiEnabled) {
            return [
                'intent' => 'unknown',
                'confidence' => 0.0,
                'source' => 'rules',
                'entities' => $entities,
            ];
        }

        $aiResult = $this->openAIClient->classifyWhatsAppIntent($message);
        $aiIntent = (string) ($aiResult['intent'] ?? 'unknown');
        $confidence = (float) ($aiResult['confidence'] ?? 0);
        $aiEntities = is_array($aiResult['entities'] ?? null) ? $aiResult['entities'] : [];
        $mergedEntities = array_merge($this->emptyEntities(), $aiEntities, $entities);

        error_log(sprintf(
            '[WhatsApp AI Intent] message="%s" intent="%s" confidence="%s" entities=%s hard_entities=%s',
            $message,
            $aiIntent,
            (string) $confidence,
            json_encode($aiEntities, JSON_UNESCAPED_UNICODE),
            json_encode($entities, JSON_UNESCAPED_UNICODE)
        ));

        $this->logger->info('OpenAI intent classifier result', [
            'message' => $message,
            'intent' => $aiIntent,
            'confidence' => $confidence,
            'entities' => $aiEntities,
            'hard_entities' => $entities,
        ]);

        if ($confidence < $this->minimumAiConfidence) {
            return [
                'intent' => 'unknown',
                'confidence' => $confidence,
                'source' => 'ai_low_confidence',
                'entities' => $entities,
            ];
        }

        return [
            'intent' => $aiIntent,
            'confidence' => $confidence,
            'source' => 'ai',
            'entities' => $mergedEntities,
        ];
    }

    /**
     * @param array<string, string|null> $entities
     */
    private function detectByRules(string $text, array $entities): string
    {
        if ($entities['folio'] !== null || $this->containsAny($text, [
            'cancelar cita',
            'cancelar mi cita',
            'cancelacion',
            'cancelar folio',
            'cancelar el folio',
            'cancelar reservacion',
            'anular reservacion',
            'anular mi cita',
            'anular cita',
            'quiero cancelar',
            'quiero cancelar mi cita',
            'quiero cancelar el folio',
            'quiero cancelar folio',
        ])) {
            if ($this->containsAny($text, ['cancelar', 'cancelacion', 'anular']) || $entities['folio'] !== null) {
                return 'cancel_appointment';
            }
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
            'corte me queda',
            'tipo de cara',
            'cara redonda',
            'cara ovalada',
            'cara cuadrada',
            'cara alargada',
            'cara diamante',
            'cara corazon',
            'recomienda un corte',
            'recomendar corte',
            'corte para cara',
            'que corte',
        ])) {
            return 'haircut_recommendation';
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
            'frizz',
            'peinar',
        ])) {
            return 'product_recommendation';
        }

        if ($this->containsAny($text, [
            'servicio',
            'servicios',
            'precio',
            'precios',
            'cuanto cuesta',
            'corte y barba',
            'barba',
            'fade',
            'desvanecido',
        ]) && !$this->containsAny($text, ['agendar', 'cita', 'reservar', 'agenda'])) {
            return 'list_services';
        }

        if ($this->containsAny($text, [
            'barbero',
            'barberos',
            'quien corta',
            'profesional',
            'estilista',
            'quien atiende',
        ]) && !$this->containsAny($text, ['agendar', 'cita', 'reservar', 'agenda'])) {
            return 'check_barbers';
        }

        if ($entities['date'] !== null || $entities['date_text'] !== null || $entities['time_text'] !== null || $this->containsAny($text, [
            'agenda',
            'cita',
            'horario',
            'horarios',
            'disponible',
            'disponibilidad',
            'reservar',
            'agendar',
            'espacio',
            'espacio disponible',
            'lugar',
            'apartado',
            'apartar',
            'reservacion',
        ])) {
            return 'check_agenda';
        }

        if ($this->containsAny($text, [
            'hola',
            'buenas',
            'buen dia',
            'hey',
            'menu',
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

    private function mapAiIntentToLegacyIntent(string $intent): string
    {
        return match ($intent) {
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
     * @return array<string, string|null>
     */
    private function extractEntitiesByRules(string $message): array
    {
        $entities = $this->emptyEntities();
        $text = $this->normalizeText($message);

        if (preg_match('/(?:folio|cita)\s*#?\s*(\d{1,10})/i', $text, $matches)) {
            $entities['folio'] = $matches[1];
        }

        $date = $this->parseDate($message);

        if ($date !== null) {
            $entities['date'] = $date;
            $entities['date_text'] = $this->extractDateText($message) ?? $date;
        }

        $timeText = $this->extractTimeText($message);

        if ($timeText !== null) {
            $entities['time_text'] = $timeText;
            $entities['time'] = $this->normalizeTime($timeText);
        }

        foreach (['redonda', 'ovalada', 'cuadrada', 'alargada', 'diamante', 'corazon'] as $shape) {
            if (str_contains($text, $shape)) {
                $entities['face_shape'] = $shape;
                break;
            }
        }

        $entities['branch_text'] = $this->extractEntityTextAfterKeywords($text, ['sucursal', 'en']);
        $entities['service_text'] = $this->extractEntityTextAfterKeywords($text, ['servicio', 'corte', 'paquete']);
        $entities['barber_text'] = $this->extractEntityTextAfterKeywords($text, ['con', 'barbero', 'estilista']);

        return $entities;
    }

    private function parseDate(string $message): ?string
    {
        $text = $this->normalizeText($message);
        $timezone = new \DateTimeZone(self::BOOKING_TIMEZONE);
        $today = new \DateTimeImmutable('today', $timezone);

        if (preg_match('/\bhoy\b/u', $text)) {
            return $today->format('Y-m-d');
        }

        if (preg_match('/\bmanana\b/u', $text)) {
            return $today->modify('+1 day')->format('Y-m-d');
        }

        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/u', $text, $matches)) {
            return $this->buildDate((int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u', $text, $matches)) {
            return $this->buildDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        $months = $this->spanishMonths();

        if (preg_match('/(?:para\s+el|para|el)?\s*(\d{1,2})\s*(?:de\s*)?([a-z]+)/u', $text, $matches)) {
            $day = (int) $matches[1];
            $monthName = $matches[2];

            if (isset($months[$monthName])) {
                $month = $months[$monthName];
                $year = (int) $today->format('Y');
                $candidate = $this->buildDate($year, $month, $day);

                if ($candidate === null) {
                    return null;
                }

                if (new \DateTimeImmutable($candidate . ' 00:00:00', $timezone) < $today) {
                    $candidate = $this->buildDate($year + 1, $month, $day);
                }

                return $candidate;
            }
        }

        if (preg_match('/(?:para\s+el|para|el)\s+(\d{1,2})\b/u', $text, $matches)) {
            return $this->buildFutureDateFromDay((int) $matches[1], $today, $timezone);
        }

        if (preg_match('/^\s*(\d{1,2})\s*$/u', $text, $matches)) {
            return $this->buildFutureDateFromDay((int) $matches[1], $today, $timezone);
        }

        return null;
    }

    private function extractDateText(string $message): ?string
    {
        $text = $this->normalizeText($message);

        foreach ([
            '/\bhoy\b/u',
            '/\bmanana\b/u',
            '/\b\d{1,2}\/\d{1,2}\/\d{4}\b/u',
            '/\b\d{4}-\d{1,2}-\d{1,2}\b/u',
            '/(?:para\s+el|para|el)?\s*\d{1,2}\s*(?:de\s*)?(?:enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\b/u',
            '/(?:para\s+el|para|el)\s+\d{1,2}\b/u',
        ] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim((string) $matches[0]);
            }
        }

        return null;
    }

    private function extractTimeText(string $message): ?string
    {
        $text = $this->normalizeText($message);

        if (preg_match('/(?:a\s+las|alas|a\s+la|la)?\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/u', $text, $matches)) {
            $hour = (int) $matches[1];

            if ($hour >= 1 && $hour <= 23) {
                return trim((string) $matches[0]);
            }
        }

        return null;
    }

    private function normalizeTime(string $timeText): ?string
    {
        $text = $this->normalizeText($timeText);

        if (!preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/u', $text, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
        $meridiem = $matches[3] ?? null;

        if ($hour < 1 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        }

        if ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function buildFutureDateFromDay(int $day, \DateTimeImmutable $today, \DateTimeZone $timezone): ?string
    {
        $month = (int) $today->format('m');
        $year = (int) $today->format('Y');
        $candidate = $this->buildDate($year, $month, $day);

        if ($candidate === null) {
            return null;
        }

        if (new \DateTimeImmutable($candidate . ' 00:00:00', $timezone) < $today) {
            $nextMonth = $today->modify('first day of next month');
            $candidate = $this->buildDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('m'), $day);
        }

        return $candidate;
    }

    private function buildDate(int $year, int $month, int $day): ?string
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @return array<string, int>
     */
    private function spanishMonths(): array
    {
        return [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];
    }

    /**
     * @param array<int, string> $keywords
     */
    private function extractEntityTextAfterKeywords(string $text, array $keywords): ?string
    {
        foreach ($keywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b\s+([a-z0-9\s]+)$/u', $text, $matches)) {
                $value = trim((string) $matches[1]);

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $this->normalizeText($needle))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-z0-9:\/\-\s#]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array{
     *     intent:string,
     *     confidence:float,
     *     source:string,
     *     entities:array<string, string|null>
     * }
     */
    private function fallbackAnalysis(): array
    {
        return [
            'intent' => 'unknown',
            'confidence' => 0.0,
            'source' => 'rules',
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
            'date' => null,
            'date_text' => null,
            'time_text' => null,
            'time' => null,
            'branch_text' => null,
            'service_text' => null,
            'barber_text' => null,
            'customer_name' => null,
            'phone' => null,
            'email' => null,
            'face_shape' => null,
            'product_need' => null,
        ];
    }
}
