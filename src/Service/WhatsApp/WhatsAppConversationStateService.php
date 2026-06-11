<?php

declare(strict_types=1);

namespace App\Service\WhatsApp;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class WhatsAppConversationStateService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getState(string $waId): ?array
    {
        $state = $this->connection->fetchAssociative(
            <<<SQL
            SELECT *
            FROM tbd_whatsapp_conversation_state
            WHERE wa_id = :waId
              AND expires_at > NOW()
            LIMIT 1
            SQL,
            ['waId' => $waId]
        );

        return $state !== false ? $state : null;
    }

    public function start(string $waId, int $companyId = 1): array
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $expiresAt = (new \DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');

        $this->connection->executeStatement(
            <<<SQL
            INSERT INTO tbd_whatsapp_conversation_state (
                wa_id,
                step,
                company_id,
                expires_at,
                created_at,
                updated_at
            ) VALUES (
                :waId,
                :step,
                :companyId,
                :expiresAt,
                :createdAt,
                :updatedAt
            )
            ON CONFLICT (wa_id)
            DO UPDATE SET
                step = EXCLUDED.step,
                company_id = EXCLUDED.company_id,
                branch_id = NULL,
                branch_name = NULL,
                branch_address = NULL,
                service_id = NULL,
                service_name = NULL,
                service_price = NULL,
                service_duration = NULL,
                date = NULL,
                barber_id = NULL,
                barber_name = NULL,
                time_label = NULL,
                scheduled_date_time = NULL,
                customer_name = NULL,
                customer_email = NULL,
                customer_phone = NULL,
                customer_country_code = NULL,
                customer_notes = NULL,
                payload_json = NULL,
                expires_at = EXCLUDED.expires_at,
                updated_at = EXCLUDED.updated_at
            SQL,
            [
                'waId' => $waId,
                'step' => 'selecting_branch',
                'companyId' => $companyId,
                'expiresAt' => $expiresAt,
                'createdAt' => $now,
                'updatedAt' => $now,
            ],
            [
                'companyId' => ParameterType::INTEGER,
            ]
        );

        return $this->getState($waId) ?? [];
    }

    public function updateState(string $waId, string $step, array $data = []): array
    {
        $allowedFields = [
            'company_id',
            'branch_id',
            'branch_name',
            'branch_address',
            'service_id',
            'service_name',
            'service_price',
            'service_duration',
            'date',
            'barber_id',
            'barber_name',
            'time_label',
            'scheduled_date_time',
            'customer_name',
            'customer_email',
            'customer_phone',
            'customer_country_code',
            'customer_notes',
            'payload_json',
        ];

        $sets = [
            'step = :step',
            'expires_at = :expiresAt',
            'updated_at = :updatedAt',
        ];

        $params = [
            'waId' => $waId,
            'step' => $step,
            'expiresAt' => (new \DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s'),
            'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = $field . ' = :' . $field;

                if ($field === 'payload_json' && is_array($data[$field])) {
                    $params[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $params[$field] = $data[$field];
                }
            }
        }

        $sql = sprintf(
            'UPDATE tbd_whatsapp_conversation_state SET %s WHERE wa_id = :waId',
            implode(', ', $sets)
        );

        $this->connection->executeStatement($sql, $params);

        return $this->getState($waId) ?? [];
    }

    public function clear(string $waId): void
    {
        $this->connection->delete(
            'tbd_whatsapp_conversation_state',
            ['wa_id' => $waId]
        );
    }

    public function clearExpired(): int
    {
        return $this->connection->executeStatement(
            'DELETE FROM tbd_whatsapp_conversation_state WHERE expires_at <= NOW()'
        );
    }
}