<?php

namespace App\Service\WhatsApp;

final class WhatsAppMessageParser
{
    public function parseIncomingMessages(array $payload): array
    {
        $messages = [];

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];

                foreach (($value['messages'] ?? []) as $message) {
                    $messages[] = [
                        'from' => $message['from'] ?? null,
                        'messageId' => $message['id'] ?? null,
                        'timestamp' => $message['timestamp'] ?? null,
                        'type' => $message['type'] ?? null,
                        'body' => $this->extractBody($message),
                        'raw' => $message,
                        'metadata' => $value['metadata'] ?? [],
                        'contacts' => $value['contacts'] ?? [],
                    ];
                }
            }
        }

        return array_values(array_filter($messages, static function (array $message): bool {
            return !empty($message['from']) && !empty($message['messageId']);
        }));
    }

    private function extractBody(array $message): ?string
    {
        $type = $message['type'] ?? null;

        return match ($type) {
            'text' => $message['text']['body'] ?? null,
            'button' => $message['button']['text'] ?? null,
            'interactive' => $this->extractInteractiveBody($message),
            default => null,
        };
    }

    private function extractInteractiveBody(array $message): ?string
    {
        $interactive = $message['interactive'] ?? [];
        $interactiveType = $interactive['type'] ?? null;

        return match ($interactiveType) {
            'button_reply' => $interactive['button_reply']['title'] ?? $interactive['button_reply']['id'] ?? null,
            'list_reply' => $interactive['list_reply']['title'] ?? $interactive['list_reply']['id'] ?? null,
            default => null,
        };
    }
}