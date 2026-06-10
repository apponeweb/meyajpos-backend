<?php

namespace App\Controller\Api\WhatsApp;

use App\Service\WhatsApp\WhatsAppBotOrchestrator;
use App\Service\WhatsApp\WhatsAppMessageParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/whatsapp')]
class WhatsAppWebhookController extends AbstractController
{
    public function __construct(
        private readonly WhatsAppMessageParser $messageParser,
        private readonly WhatsAppBotOrchestrator $botOrchestrator,
        private readonly string $whatsappVerifyToken
    ) {
    }

    #[Route('/webhook', name: 'api_whatsapp_webhook_verify', methods: ['GET'])]
    public function verify(Request $request): Response
    {
        $mode = $request->query->get('hub_mode') ?? $request->query->get('hub.mode');
        $token = $request->query->get('hub_verify_token') ?? $request->query->get('hub.verify_token');
        $challenge = $request->query->get('hub_challenge') ?? $request->query->get('hub.challenge');

        if ($mode === 'subscribe' && $token === $this->whatsappVerifyToken) {
            return new Response($challenge ?? '', Response::HTTP_OK);
        }

        return new Response('Token de verificación inválido', Response::HTTP_FORBIDDEN);
    }

    #[Route('/webhook', name: 'api_whatsapp_webhook_receive', methods: ['POST'])]
    public function receive(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload inválido'], Response::HTTP_BAD_REQUEST);
        }

        $messages = $this->messageParser->parseIncomingMessages($payload);

        foreach ($messages as $message) {
            $this->botOrchestrator->handleIncomingMessage($message, $payload);
        }

        return $this->json(['status' => 'received'], Response::HTTP_OK);
    }
}