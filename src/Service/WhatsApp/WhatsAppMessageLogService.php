<?php

declare(strict_types=1);

namespace App\Service\WhatsApp;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final class WhatsAppMessageLogService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function log(string $waId, string $direction, string $body): void
    {
        $waId = trim($waId);
        $body = trim($body);

        if ($waId === '' || $body === '') {
            return;
        }

        try {
            $this->connection->executeStatement(
                'INSERT INTO tbd_whatsapp_message (wa_id, direction, body, created_at) VALUES (:waId, :direction, :body, NOW())',
                [
                    'waId' => $waId,
                    'direction' => $direction === 'out' ? 'out' : 'in',
                    'body' => mb_substr($body, 0, 4000),
                ]
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('No se pudo registrar mensaje de WhatsApp.', [
                'wa_id' => $waId,
                'direction' => $direction,
                'detail' => $exception->getMessage(),
            ]);
        }
    }
}
