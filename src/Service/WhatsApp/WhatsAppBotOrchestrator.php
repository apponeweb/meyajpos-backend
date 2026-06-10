<?php

namespace App\Service\WhatsApp;

use Psr\Log\LoggerInterface;

final class WhatsAppBotOrchestrator
{
    public function __construct(
        private readonly WhatsAppClient $whatsAppClient,
        private readonly IntentClassifier $intentClassifier,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handleIncomingMessage(array $message, array $payload): void
    {
        $from = $message['from'] ?? null;
        $body = trim((string)($message['body'] ?? ''));

        if (!$from) {
            return;
        }

        try {
            $intent = $this->intentClassifier->detect($body);

            $responseText = match ($intent) {
                'check_agenda' => 'Claro. ¿Para qué día quieres revisar disponibilidad? Puedes escribir: hoy, mañana o una fecha como 12/06/2026.',
                'check_barbers' => 'Claro. ¿Para qué día quieres saber qué barberos están disponibles?',
                'reschedule' => 'Claro. Para reagendar necesito ubicar tu cita. ¿La cita está registrada con este mismo número de WhatsApp?',
                'list_services' => 'Claro. Te comparto los servicios disponibles en un momento.',
                'product_recommendation' => 'Claro. ¿Buscas producto para peinar, barba, fijación fuerte o acabado natural?',
                'haircut_recommendation' => 'Claro. ¿Qué tipo de cara tienes: ovalada, redonda, cuadrada, alargada, diamante o corazón?',
                default => "Hola 👋 Soy el asistente de la barbería.\n\nPuedo ayudarte con:\n1. Ver horarios disponibles\n2. Ver barberos disponibles\n3. Reagendar una cita\n4. Ver servicios\n5. Recomendar productos\n6. Recomendar un corte según tu tipo de cara",
            };

            $this->whatsAppClient->sendTextMessage($from, $responseText);
        } catch (\Throwable $exception) {
            $this->logger->error('Error procesando mensaje de WhatsApp', [
                'exception' => $exception->getMessage(),
                'message' => $message,
            ]);
        }
    }
}