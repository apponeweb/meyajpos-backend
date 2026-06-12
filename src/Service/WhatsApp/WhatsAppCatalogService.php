<?php

namespace App\Service\WhatsApp;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class WhatsAppCatalogService
{
    private const BOOKING_TIMEZONE = 'America/Mexico_City';

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
                COALESCE(
                    MAX(NULLIF(bs.duration_override_minutes, 0)),
                    60
                ) AS duration
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

        if (!$this->isBranchOpen($branchId, $date, $dayOfWeek)) {
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
                        (CAST(:date AS date) + bsched.open_time) < bto.end_at_local
                        AND (CAST(:date AS date) + bsched.close_time) > bto.start_at_local
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

    public function getAvailableSlots(int $barberId, int $branchId, string $date, int $productId): array
    {
        $dayOfWeek = $this->getDayOfWeek($date);

        if ($dayOfWeek === null) {
            return [];
        }

        if (!$this->isBranchOpen($branchId, $date, $dayOfWeek)) {
            return [];
        }

        $barberService = $this->connection->fetchAssociative(
            <<<SQL
            SELECT
                id,
                COALESCE(NULLIF(duration_override_minutes, 0), 60) AS duration
            FROM tbr_barber_service
            WHERE barber_user_id = :barberId
              AND product_id = :productId
              AND is_active = true
            LIMIT 1
            SQL,
            [
                'barberId' => $barberId,
                'productId' => $productId,
            ],
            [
                'barberId' => ParameterType::INTEGER,
                'productId' => ParameterType::INTEGER,
            ]
        );

        if ($barberService === false) {
            return [];
        }

        $schedule = $this->connection->fetchAssociative(
            <<<SQL
            SELECT
                open_time,
                close_time,
                COALESCE(NULLIF(slot_minutes, 0), 30) AS slot_minutes,
                COALESCE(NULLIF(turn_duration, 0), 60) AS turn_duration
            FROM tbd_barber_schedules
            WHERE barber_user_id = :barberId
              AND branch_id = :branchId
              AND day_of_week = :dayOfWeek
              AND valid_from <= :date
              AND (valid_to IS NULL OR valid_to >= :date)
            ORDER BY id ASC
            LIMIT 1
            SQL,
            [
                'barberId' => $barberId,
                'branchId' => $branchId,
                'dayOfWeek' => $dayOfWeek,
                'date' => $date,
            ],
            [
                'barberId' => ParameterType::INTEGER,
                'branchId' => ParameterType::INTEGER,
                'dayOfWeek' => ParameterType::INTEGER,
            ]
        );

        if ($schedule === false) {
            return [];
        }

        /*
         * Regla operativa BookinPOS:
         * slot_minutes = gap/espacio entre clientes.
         * turn_duration = duración real del servicio configurada en el horario.
         *
         * Ejemplo:
         * slot_minutes = 30
         * turn_duration = 60
         *
         * Resultado:
         * 11:00 AM - 12:00 PM
         * 12:30 PM - 01:30 PM
         * 02:00 PM - 03:00 PM
         */
        $slotMinutes = (int) ($schedule['slot_minutes'] ?? 30);
        $scheduleTurnDuration = (int) ($schedule['turn_duration'] ?? 60);
        $serviceDuration = (int) ($barberService['duration'] ?? 60);

        if ($slotMinutes <= 0) {
            $slotMinutes = 30;
        }

        if ($scheduleTurnDuration <= 0) {
            $scheduleTurnDuration = 60;
        }

        if ($serviceDuration <= 0) {
            $serviceDuration = 60;
        }

        /*
         * La duración real del bloque visible sale de la configuración del horario
         * y del servicio del barbero. Conservamos la mayor para evitar rangos más
         * cortos que la duración configurada para ese servicio.
         */
        $turnDuration = max($scheduleTurnDuration, $serviceDuration);
        $gapMinutes = max(0, $slotMinutes);

        $occupiedRanges = $this->getOccupiedRanges($barberId, $branchId, $date, $turnDuration, $gapMinutes);

        $slots = $this->generateSlots(
            $date,
            (string) $schedule['open_time'],
            (string) $schedule['close_time'],
            $gapMinutes,
            $turnDuration,
            $occupiedRanges
        );

        return $this->groupSlots($slots);
    }

    public function getSlotByOption(int $barberId, int $branchId, string $date, int $productId, int $option): ?array
    {
        $slotGroups = $this->getAvailableSlots($barberId, $branchId, $date, $productId);
        $slots = $this->flattenSlotGroups($slotGroups);

        return $slots[$option - 1] ?? null;
    }

    public function flattenSlotGroups(array $slotGroups): array
    {
        $slots = [];

        foreach ($slotGroups as $group) {
            foreach (($group['times'] ?? []) as $timeLabel) {
                $slots[] = [
                    'group' => (string) ($group['id'] ?? ''),
                    'time' => (string) $timeLabel,
                ];
            }
        }

        return $slots;
    }

    public function formatAvailableSlotsMenu(string $barberName, string $date, array $slotGroups): string
    {
        $slots = $this->flattenSlotGroups($slotGroups);

        if ($slots === []) {
            return sprintf(
                "Por el momento no encontré horarios disponibles con *%s* el *%s*.\n\nPuedes intentar con otra fecha escribiendo, por ejemplo:\n*mañana*\n*18/06/2026*",
                $barberName,
                $date
            );
        }

        $lines = [
            sprintf(
                'Estos horarios están disponibles con *%s* el *%s*:',
                $barberName,
                $date
            ),
            '',
        ];

        foreach ($slots as $index => $slot) {
            $lines[] = sprintf(
                '%d. %s',
                $index + 1,
                (string) $slot['time']
            );
        }

        $lines[] = '';
        $lines[] = 'Responde con el número del horario.';

        return implode("\n", $lines);
    }

    public function buildScheduledDateTime(string $date, string $timeLabel): string
    {
        return sprintf('%s %s', $date, $timeLabel);
    }

    private function isBranchOpen(int $branchId, string $date, int $dayOfWeek): bool
    {
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

        return (bool) $branchIsOpen;
    }

    private function getOccupiedRanges(int $barberId, int $branchId, string $date, int $turnDuration, int $gapMinutes): array
    {
        $occupiedRanges = [];

        try {
            $timeOffs = $this->connection->fetchAllAssociative(
                <<<SQL
                SELECT
                    start_at_local,
                    end_at_local
                FROM tbd_barber_time_off
                WHERE barber_user_id = :barberId
                  AND (branch_id = :branchId OR branch_id IS NULL)
                  AND start_at_local <= (CAST(:date AS date) + time '23:59:59')
                  AND end_at_local >= (CAST(:date AS date) + time '00:00:00')
                SQL,
                [
                    'barberId' => $barberId,
                    'branchId' => $branchId,
                    'date' => $date,
                ],
                [
                    'barberId' => ParameterType::INTEGER,
                    'branchId' => ParameterType::INTEGER,
                ]
            );

            foreach ($timeOffs as $timeOff) {
                $occupiedRanges[] = [
                    'start' => new \DateTimeImmutable((string) $timeOff['start_at_local'], new \DateTimeZone(self::BOOKING_TIMEZONE)),
                    'end' => new \DateTimeImmutable((string) $timeOff['end_at_local'], new \DateTimeZone(self::BOOKING_TIMEZONE)),
                ];
            }
        } catch (\Throwable) {
            /*
             * Si falla la lectura de bloqueos, no detenemos el flujo conversacional.
             * El endpoint final de reserva volverá a validar traslapes.
             */
        }

        try {
            $sales = $this->connection->fetchAllAssociative(
                <<<SQL
                SELECT
                    s.sale_date
                FROM tbd_sale s
                INNER JOIN tbd_sale_detail d ON d.sale_id = s.id
                WHERE d.service_provider_id = :barberId
                  AND s.sale_date >= :startOfDay
                  AND s.sale_date <= :endOfDay
                  AND s.status != :cancelledStatus
                SQL,
                [
                    'barberId' => $barberId,
                    'startOfDay' => $date . ' 00:00:00',
                    'endOfDay' => $date . ' 23:59:59',
                    'cancelledStatus' => 'CANCELLED',
                ],
                [
                    'barberId' => ParameterType::INTEGER,
                ]
            );

            foreach ($sales as $sale) {
                $start = new \DateTimeImmutable((string) $sale['sale_date'], new \DateTimeZone(self::BOOKING_TIMEZONE));

                $occupiedRanges[] = [
                    'start' => $start,
                    'end' => $start->modify(sprintf('+%d minutes', $turnDuration + $gapMinutes)),
                ];
            }
        } catch (\Throwable) {
            /*
             * La disponibilidad de citas se vuelve a validar al reservar.
             */
        }

        try {
            $appointments = $this->connection->fetchAllAssociative(
                <<<SQL
                SELECT
                    aps.scheduled_date_time,
                    aps.duration
                FROM tbd_appointment_service aps
                INNER JOIN tbd_barber_profile bp ON bp.id = aps.barber_id
                INNER JOIN tbd_appointment a ON a.id = aps.appointment_id
                WHERE bp.barber_user_id = :barberId
                  AND aps.scheduled_date_time >= :startOfDay
                  AND aps.scheduled_date_time <= :endOfDay
                  AND a.status != :cancelledStatus
                SQL,
                [
                    'barberId' => $barberId,
                    'startOfDay' => $date . ' 00:00:00',
                    'endOfDay' => $date . ' 23:59:59',
                    'cancelledStatus' => 3,
                ],
                [
                    'barberId' => ParameterType::INTEGER,
                ]
            );

            foreach ($appointments as $appointment) {
                $duration = (int) ($appointment['duration'] ?? $turnDuration);
                $start = new \DateTimeImmutable((string) $appointment['scheduled_date_time'], new \DateTimeZone(self::BOOKING_TIMEZONE));

                $occupiedRanges[] = [
                    'start' => $start,
                    'end' => $start->modify(sprintf('+%d minutes', ($duration > 0 ? $duration : $turnDuration) + $gapMinutes)),
                ];
            }
        } catch (\Throwable) {
            /*
             * Si las columnas de AppointmentService cambian, el endpoint final sigue protegiendo la reserva.
             */
        }

        return $occupiedRanges;
    }

    private function generateSlots(
        string $date,
        string $openTime,
        string $closeTime,
        int $gapMinutes,
        int $turnDuration,
        array $occupiedRanges
    ): array {
        $slots = [];

        $timezone = new \DateTimeZone(self::BOOKING_TIMEZONE);

        $currentTime = new \DateTimeImmutable(sprintf('%s %s', $date, $openTime), $timezone);
        $endTime = new \DateTimeImmutable(sprintf('%s %s', $date, $closeTime), $timezone);
        $now = new \DateTimeImmutable('now', $timezone);
        $stepMinutes = max(1, $turnDuration + max(0, $gapMinutes));

        while ($currentTime < $endTime) {
            $slotStart = $currentTime;
            $slotEnd = $currentTime->modify(sprintf('+%d minutes', $turnDuration));
            $slotOperationalEnd = $slotEnd->modify(sprintf('+%d minutes', max(0, $gapMinutes)));

            /*
             * El servicio debe terminar dentro del horario laboral. El gap posterior
             * puede quedar al final de la jornada porque ya no atiende a otro cliente.
             */
            if ($slotEnd > $endTime) {
                break;
            }

            /*
             * Si la fecha es hoy, no mostramos horarios que ya pasaron.
             */
            if ($slotStart <= $now) {
                $currentTime = $currentTime->modify(sprintf('+%d minutes', $stepMinutes));
                continue;
            }

            if (!$this->hasOverlap($slotStart, $slotOperationalEnd, $occupiedRanges)) {
                $slots[] = $slotStart->format('h:i A') . ' - ' . $slotEnd->format('h:i A');
            }

            /*
             * El siguiente inicio avanza por duración del servicio + gap.
             */
            $currentTime = $currentTime->modify(sprintf('+%d minutes', $stepMinutes));
        }

        return $slots;
    }

    private function hasOverlap(\DateTimeImmutable $slotStart, \DateTimeImmutable $slotEnd, array $occupiedRanges): bool
    {
        foreach ($occupiedRanges as $range) {
            $rangeStart = $range['start'];
            $rangeEnd = $range['end'];

            if (!$rangeStart instanceof \DateTimeInterface || !$rangeEnd instanceof \DateTimeInterface) {
                continue;
            }

            if ($slotStart < $rangeEnd && $slotEnd > $rangeStart) {
                return true;
            }
        }

        return false;
    }

    private function groupSlots(array $slots): array
    {
        $groups = [
            ['id' => 'Mañana', 'icon' => 'Sun', 'times' => []],
            ['id' => 'Tarde', 'icon' => 'CloudSun', 'times' => []],
            ['id' => 'Noche', 'icon' => 'Moon', 'times' => []],
        ];

        foreach ($slots as $timeLabel) {
            $startTimeLabel = explode(' - ', $timeLabel)[0] ?? $timeLabel;
            $time = \DateTimeImmutable::createFromFormat('h:i A', $startTimeLabel);

            if (!$time) {
                continue;
            }

            $hour = (int) $time->format('H');

            if ($hour < 12) {
                $groups[0]['times'][] = $timeLabel;
            } elseif ($hour < 17) {
                $groups[1]['times'][] = $timeLabel;
            } else {
                $groups[2]['times'][] = $timeLabel;
            }
        }

        return array_values(array_filter(
            $groups,
            static fn (array $group): bool => !empty($group['times'])
        ));
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
