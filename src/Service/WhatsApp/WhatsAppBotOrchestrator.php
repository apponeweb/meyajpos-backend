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
        $body = trim((string) ($message['body'] ?? ''));

        if (!$from) {
            return;
        }

        try {
            $intent = $this->intentClassifier->detect($body);

            $responseText = match ($intent) {
                'greeting' => $this->mainMenu(),
                'check_agenda' => $this->agendaResponse($body),
                'check_barbers' => $this->barbersResponse(),
                'reschedule' => $this->rescheduleResponse(),
                'list_services' => $this->servicesResponse(),
                'product_recommendation' => $this->productsResponse($body),
                'haircut_recommendation' => $this->haircutRecommendationResponse($body),
                default => $this->unknownResponse(),
            };

            $result = $this->whatsAppClient->sendTextMessage($from, $responseText);

            $this->logger->info('WhatsApp bot response sent', [
                'to' => $from,
                'body' => $body,
                'intent' => $intent,
                'result' => $result,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Error procesando mensaje de WhatsApp', [
                'exception' => $exception->getMessage(),
                'message' => $message,
            ]);
        }
    }

    private function mainMenu(): string
    {
        return "Hola 👋 Soy el asistente de la barbería.\n\n"
            . "Puedo ayudarte con:\n"
            . "1. Ver horarios disponibles\n"
            . "2. Ver barberos disponibles\n"
            . "3. Reagendar una cita\n"
            . "4. Ver servicios\n"
            . "5. Recomendar productos\n"
            . "6. Recomendar un corte según tu tipo de cara\n\n"
            . "Escribe por ejemplo:\n"
            . "- servicios\n"
            . "- agenda mañana\n"
            . "- barberos\n"
            . "- productos\n"
            . "- corte para cara redonda";
    }

    private function servicesResponse(): string
    {
        return "Estos son algunos servicios disponibles:\n\n"
            . "1. Corte clásico\n"
            . "2. Corte y barba\n"
            . "3. Arreglo de barba\n"
            . "4. Fade / desvanecido\n"
            . "5. Corte infantil\n\n"
            . "Para revisar horarios escribe: agenda mañana";
    }

    private function agendaResponse(string $message): string
    {
        $text = mb_strtolower($message);

        if (str_contains($text, 'hoy')) {
            return "Claro. Para hoy puedo ayudarte a revisar disponibilidad.\n\n"
                . "Por ahora indícame el servicio que necesitas:\n"
                . "1. Corte\n"
                . "2. Corte y barba\n"
                . "3. Barba\n\n"
                . "Ejemplo: quiero corte hoy";
        }

        if (str_contains($text, 'mañana') || str_contains($text, 'manana')) {
            return "Claro. Para mañana puedo ayudarte a revisar disponibilidad.\n\n"
                . "Indícame el servicio y si prefieres algún barbero.\n\n"
                . "Ejemplo: corte y barba mañana con cualquier barbero";
        }

        return "Claro. Para revisar disponibilidad dime el día que prefieres.\n\n"
            . "Puedes escribir:\n"
            . "- agenda hoy\n"
            . "- agenda mañana\n"
            . "- agenda viernes\n"
            . "- agenda 15/06/2026";
    }

    private function barbersResponse(): string
    {
        return "Puedo ayudarte a consultar barberos disponibles.\n\n"
            . "Por ahora dime el día y el servicio que buscas.\n\n"
            . "Ejemplo:\n"
            . "barberos disponibles mañana para corte";
    }

    private function rescheduleResponse(): string
    {
        return "Claro, puedo ayudarte a reagendar.\n\n"
            . "Para ubicar tu cita necesito:\n"
            . "1. Nombre con el que se registró\n"
            . "2. Día original de la cita\n"
            . "3. Nuevo día u horario deseado\n\n"
            . "Ejemplo:\n"
            . "Reagendar Ernesto, cita de hoy, para mañana a las 5";
    }

    private function productsResponse(string $message): string
    {
        $text = mb_strtolower($message);

        if (str_contains($text, 'barba')) {
            return "Para barba te recomiendo preguntar en sucursal por:\n\n"
                . "1. Aceite para barba\n"
                . "2. Bálsamo para barba\n"
                . "3. Shampoo para barba\n\n"
                . "Te ayudan a hidratar, dar forma y reducir resequedad.";
        }

        if (
            str_contains($text, 'fijacion')
            || str_contains($text, 'fijación')
            || str_contains($text, 'pomada')
            || str_contains($text, 'cera')
            || str_contains($text, 'gel')
        ) {
            return "Para peinado con fijación puedes buscar:\n\n"
                . "1. Pomada de fijación media\n"
                . "2. Cera con acabado natural\n"
                . "3. Gel de fijación fuerte\n\n"
                . "Si quieres acabado natural, pide cera o pomada mate.";
        }

        return "Puedo recomendarte productos según lo que necesitas:\n\n"
            . "1. Fijación fuerte\n"
            . "2. Acabado natural\n"
            . "3. Cuidado de barba\n"
            . "4. Cabello seco\n"
            . "5. Control de frizz\n\n"
            . "Ejemplo: producto para fijación fuerte";
    }

    private function haircutRecommendationResponse(string $message): string
    {
        $text = mb_strtolower($message);

        if (str_contains($text, 'redonda')) {
            return "Para cara redonda suelen favorecer cortes con volumen arriba y laterales más cortos:\n\n"
                . "1. Quiff texturizado\n"
                . "2. Pompadour bajo\n"
                . "3. Fade medio con volumen superior\n"
                . "4. Side part con laterales limpios\n\n"
                . "La idea es alargar visualmente el rostro.";
        }

        if (str_contains($text, 'ovalada')) {
            return "Para cara ovalada hay bastante libertad. Opciones recomendadas:\n\n"
                . "1. Crop texturizado\n"
                . "2. Fade bajo\n"
                . "3. Slick back\n"
                . "4. Corte clásico con raya lateral";
        }

        if (str_contains($text, 'cuadrada')) {
            return "Para cara cuadrada funcionan bien cortes que respetan la mandíbula marcada:\n\n"
                . "1. Buzz cut con fade\n"
                . "2. Crew cut\n"
                . "3. Side part clásico\n"
                . "4. French crop";
        }

        if (str_contains($text, 'alargada')) {
            return "Para cara alargada conviene evitar demasiado volumen arriba. Opciones recomendadas:\n\n"
                . "1. Corte medio texturizado\n"
                . "2. Flequillo ligero\n"
                . "3. Side part bajo\n"
                . "4. Taper clásico";
        }

        if (str_contains($text, 'diamante')) {
            return "Para cara tipo diamante conviene equilibrar pómulos y frente:\n\n"
                . "1. Fringe texturizado\n"
                . "2. Taper con volumen medio\n"
                . "3. Corte en capas\n"
                . "4. Side swept";
        }

        if (str_contains($text, 'corazon') || str_contains($text, 'corazón')) {
            return "Para cara tipo corazón conviene equilibrar frente y mentón:\n\n"
                . "1. Flequillo ligero\n"
                . "2. Taper bajo\n"
                . "3. Corte texturizado medio\n"
                . "4. Side part suave";
        }

        return "Claro. Te puedo recomendar cortes según tu tipo de cara.\n\n"
            . "Dime si tu cara es:\n"
            . "1. Ovalada\n"
            . "2. Redonda\n"
            . "3. Cuadrada\n"
            . "4. Alargada\n"
            . "5. Diamante\n"
            . "6. Corazón\n\n"
            . "Ejemplo: corte para cara redonda";
    }

    private function unknownResponse(): string
    {
        return "No estoy seguro de haber entendido tu mensaje.\n\n"
            . "Puedes escribir:\n"
            . "- servicios\n"
            . "- agenda\n"
            . "- barberos\n"
            . "- reagendar\n"
            . "- productos\n"
            . "- corte para cara redonda";
    }
}