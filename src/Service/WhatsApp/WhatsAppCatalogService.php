<?php

namespace App\Service\WhatsApp;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;


class WhatsAppCatalogService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getServicesMenu(?int $limit = 10): string
    {
        try {
            $services = $this->connection->fetchAllAssociative(
                <<<SQL
                SELECT
                    id,
                    name,
                    price
                FROM tbd_master_product
                WHERE is_active = true
                  AND deleted_at IS NULL
                  AND service_type_id = 1
                ORDER BY name ASC
                LIMIT :limit
                SQL,
                ['limit' => $limit],
                ['limit' => ParameterType::INTEGER]
            );
        } catch (\Throwable) {
            return "Por el momento no pude consultar el catálogo de servicios.\n\nIntenta de nuevo en unos minutos o escribe *hola* para ver el menú.";
        }

        if ($services === []) {
            return "Por el momento no tengo servicios disponibles para mostrar.\n\nEscribe *hola* para ver el menú.";
        }

        $lines = [
            'Estos son nuestros servicios disponibles:',
            '',
        ];

        foreach ($services as $index => $service) {
            $name = trim((string) ($service['name'] ?? 'Servicio'));
            $price = (float) ($service['price'] ?? 0);

            $priceText = $price > 0
                ? '$' . number_format($price, 2)
                : 'Precio por confirmar';

            $lines[] = sprintf(
                '%d. %s - %s',
                $index + 1,
                $name,
                $priceText
            );
        }

        $lines[] = '';
        $lines[] = 'Para consultar horarios escribe:';
        $lines[] = '*agenda mañana*';
        $lines[] = '';
        $lines[] = 'Para ver barberos escribe:';
        $lines[] = '*barberos*';

        return implode("\n", $lines);
    }
}