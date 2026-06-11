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
                COALESCE(MAX(bs.duration_override_minutes), 60) AS duration
            FROM tbd_branch_product bp
            INNER JOIN tbd_master_product mp ON mp.id = bp.product_id
            LEFT JOIN tbr_barber_service bs ON bs.product_id = mp.id
            WHERE bp.branch_id = :branchId
              AND bp.enabled = true
              AND mp.is_active = true
              AND mp.deleted_at IS NULL
              AND mp.service_type_id = 1
            GROUP BY mp.id, mp.name, mp.price
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

    public function getAvailableBarbers(int $branchId, string $date, int $productId): array
    {
        $dayOfWeek = $this->getDayOfWeek($date);

        if ($dayOfWeek === null) {
            return [];
        }

        $branchIsOpen = $this->connection->fetchOne(
            <<<SQL
            SELECT 1
            FROM tbd_branch_hours bh
            WHERE bh.branch_id = :branchId
              AND bh.day_of_week = :dayOfWeek
              AND bh.valid_from <= :date
              AND (bh.valid_to IS NULL OR bh.valid_to >= :date)
            LIMIT 1
            SQL,
            [
                'branchId' => $branchId,
                'dayOfWeek' => $dayOfWeek,
                'date' => $date,
            ],
            [
                'branchId' => ParameterType::INTEGER,
                'dayOfWeek' => ParameterType::INTEGER,
            ]
        );

        if (!$branchIsOpen) {
            return [];
        }

        $barbers = $this->connection->fetchAllAssociative(
            <<<SQL
            SELECT DISTINCT
                u.id,
                TRIM(CONCAT(u.name, ' ', COALESCE(u.last_name, ''))) AS name,
                p.photo_url,
                p.avg_rating,
                p.rating_count,
                p.classification,
                p.experience,
                bsched.open_time,
                bsched.close_time
            FROM public."user" u
            LEFT JOIN tbd_barber_profile p ON p.barber_user_id = u.id
            INNER JOIN tbr_barber_service bserv ON bserv.barber_user_id = u.id
            INNER JOIN tbd_barber_schedules bsched ON bsched.barber_user_id = u.id
            WHERE u.barber_sn = true
              AND u.enabled = true
              AND bserv.product_id = :productId
              AND bserv.is_active = true
              AND bsched.branch_id = :branchId
              AND bsched.day_of_week = :dayOfWeek
              AND bsched.valid_from <= :date
              AND (bsched.valid_to IS NULL OR bsched.valid_to >= :date)
              AND NOT EXISTS (
                  SELECT 1
                  FROM tbd_barber_time_off bto
                  WHERE bto.barber_user_id = u.id
                    AND (bto.branch_id = :branchId OR bto.branch_id IS NULL)
                    AND (
                        (:date::date + bsched.open_time) < bto.end_at_local
                        AND (:date::date + bsched.close_time) > bto.start_at_local
                    )
              )
            ORDER BY name ASC
            SQL,
            [
                'branchId' => $branchId,
                'date' => $date,
                'productId' => $productId,
                'dayOfWeek' => $dayOfWeek,
            ],
            [
                'branchId' => ParameterType::INTEGER,
                'productId' => ParameterType::INTEGER,
                'dayOfWeek' => ParameterType::INTEGER,
            ]
        );

        return array_map(function (array $barber): array {
            $specialties = $this->getBarberSpecialties((int) $barber['id']);

            return [
                'id' => (int) $barber['id'],
                'name' => trim((string) ($barber['name'] ?? 'Barbero')),
                'role' => (string) ($barber['classification'] ?? ''),
                'experience' => $this->formatExperience($barber['experience'] ?? null),
                'rating' => (float) ($barber['avg_rating'] ?? 0),
                'reviewCount' => (int) ($barber['rating_count'] ?? 0),
                'image' => $barber['photo_url'] ?? null,
                'specialties' => $specialties,
            ];
        }, $barbers);
    }

    public function getAvailableBarberByOption(int $branchId, string $date, int $productId, int $option): ?array
    {
        $barbers = $this->getAvailableBarbers($branchId, $date, $productId);

        return $barbers[$option - 1] ?? null;
    }

    public function formatAvailableBarbersMenu(
        string $branchName,
        string $serviceName,
        string $date,
        array $barbers
    ): string {
        if ($barbers === []) {
            return sprintf(
                "Por el momento no encontré barberos disponibles para:\n\nSucursal: *%s*\nServicio: *%s*\nFecha: *%s*\n\nPuedes intentar con otra fecha escribiendo, por ejemplo:\n*mañana*\n*18/06/2026*",
                $branchName,
                $serviceName,
                $date
            );
        }

        $lines = [
            sprintf(
                'Estos barberos están disponibles para *%s* en *%s* el *%s*:',
                $serviceName,
                $branchName,
                $date
            ),
            '',
        ];

        foreach ($barbers as $index => $barber) {
            $name = trim((string) ($barber['name'] ?? 'Barbero'));
            $rating = (float) ($barber['rating'] ?? 0);
            $reviewCount = (int) ($barber['reviewCount'] ?? 0);

            $ratingText = $reviewCount > 0
                ? sprintf('⭐ %.1f (%d reseñas)', $rating, $reviewCount)
                : 'Sin reseñas aún';

            $specialties = $barber['specialties'] ?? [];
            $specialtyText = '';

            if (is_array($specialties) && $specialties !== []) {
                $specialtyText = ' - ' . implode(', ', array_slice($specialties, 0, 3));
            }

            $lines[] = sprintf(
                '%d. %s - %s%s',
                $index + 1,
                $name,
                $ratingText,
                $specialtyText
            );
        }

        $lines[] = '';
        $lines[] = 'Responde con el número del barbero.';

        return implode("\n", $lines);
    }

    private function getBarberSpecialties(int $barberId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                <<<SQL
                SELECT s.name
                FROM tbr_barber_specialty bs
                INNER JOIN tbn_specialty s ON s.id = bs.specialty_id
                WHERE bs.barber_user_id = :barberId
                ORDER BY s.name ASC
                SQL,
                ['barberId' => $barberId],
                ['barberId' => ParameterType::INTEGER]
            );

            return array_map(
                static fn (array $row): string => (string) $row['name'],
                $rows
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function formatExperience(mixed $experience): ?string
    {
        if ($experience === null || $experience === '') {
            return null;
        }

        $experienceValue = (int) $experience;

        if ($experienceValue <= 0) {
            return null;
        }

        return $experienceValue === 1
            ? '1 año de experiencia'
            : $experienceValue . ' años de experiencia';
    }

    private function getDayOfWeek(string $date): ?int
    {
        try {
            $dateTime = new \DateTimeImmutable($date);

            return (int) $dateTime->format('N');
        } catch (\Throwable) {
            return null;
        }
    }
}