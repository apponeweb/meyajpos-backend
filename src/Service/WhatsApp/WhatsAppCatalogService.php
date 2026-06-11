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

    public function getBranchesByCompany(int $companyId): array
{
    return $this->connection->fetchAllAssociative(
        <<<SQL
        SELECT
            id,
            name,
            address
        FROM tbn_branch
        WHERE company_id = :companyId
          AND is_active = true
          AND deleted_at IS NULL
        ORDER BY name ASC
        SQL,
        ['companyId' => $companyId],
        ['companyId' => ParameterType::INTEGER]
    );
}

public function getBranchByOption(int $companyId, int $option): ?array
{
    $branches = $this->getBranchesByCompany($companyId);

    return $branches[$option - 1] ?? null;
}

public function formatBranchesMenu(array $branches): string
{
    if ($branches === []) {
        return "Por el momento no encontré sucursales disponibles para agendar.\n\nEscribe *hola* para volver al menú.";
    }

    $lines = [
        '¿En qué sucursal quieres agendar?',
        '',
    ];

    foreach ($branches as $index => $branch) {
        $lines[] = sprintf(
            '%d. %s',
            $index + 1,
            (string) $branch['name']
        );
    }

    $lines[] = '';
    $lines[] = 'Responde con el número de la sucursal.';

    return implode("\n", $lines);
}

public function getServicesByBranch(int $branchId): array
{
    return $this->connection->fetchAllAssociative(
        <<<SQL
        SELECT
            mp.id,
            mp.name,
            mp.price,
            COALESCE(bs.duration_override_minutes, 60) AS duration
        FROM tbd_branch_product bp
        INNER JOIN tbd_master_product mp ON mp.id = bp.product_id
        LEFT JOIN tbr_barber_service bs ON bs.product_id = mp.id
        WHERE bp.branch_id = :branchId
          AND bp.enabled = true
          AND bp.deleted_at IS NULL
          AND mp.is_active = true
          AND mp.deleted_at IS NULL
          AND mp.service_type_id = 1
        GROUP BY mp.id, mp.name, mp.price, bs.duration_override_minutes
        ORDER BY mp.name ASC
        SQL,
        ['branchId' => $branchId],
        ['branchId' => ParameterType::INTEGER]
    );
}

public function getServiceByOption(int $branchId, int $option): ?array
{
    $services = $this->getServicesByBranch($branchId);

    return $services[$option - 1] ?? null;
}

public function formatServicesMenu(string $branchName, array $services): string
{
    if ($services === []) {
        return sprintf(
            "Por el momento no encontré servicios disponibles en *%s*.\n\nEscribe *agenda* para iniciar de nuevo.",
            $branchName
        );
    }

    $lines = [
        sprintf('Estos son los servicios disponibles en *%s*:', $branchName),
        '',
    ];

    foreach ($services as $index => $service) {
        $price = number_format((float) $service['price'], 2);

        $lines[] = sprintf(
            '%d. %s - $%s',
            $index + 1,
            (string) $service['name'],
            $price
        );
    }

    $lines[] = '';
    $lines[] = 'Responde con el número del servicio.';

    return implode("\n", $lines);
}


}