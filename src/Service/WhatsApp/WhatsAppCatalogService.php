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
                $this->formatDateForDisplay($date)
            );
        }

        $lines = [
            sprintf(
                'Estos barberos están disponibles para *%s* en *%s* el *%s*:',
                $serviceName,
                $branchName,
                $this->formatDateForDisplay($date)
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
         * Regla correcta:
         * slot_minutes = cada cuántos minutos puede iniciar una cita.
         * turn_duration / duración del servicio = cuánto dura realmente la cita.
         *
         * Ejemplo:
         * slot_minutes = 30
         * turn_duration = 60
         *
         * Resultado:
         * 12:00 PM - 01:00 PM
         * 12:30 PM - 01:30 PM
         * 01:00 PM - 02:00 PM
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
         * Si la duración del servicio cambió a 60, pero el schedule sigue en 30,
         * usamos la duración mayor para no mostrar slots visuales de 30 min.
         */
        $turnDuration = max($scheduleTurnDuration, $serviceDuration);

        $occupiedRanges = $this->getOccupiedRanges($barberId, $branchId, $date, $turnDuration);

        $slots = $this->generateSlots(
            $date,
            (string) $schedule['open_time'],
            (string) $schedule['close_time'],
            $slotMinutes,
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
                $this->formatDateForDisplay($date)
            );
        }

        $lines = [
            sprintf(
                'Estos horarios están disponibles con *%s* el *%s*:',
                $barberName,
                $this->formatDateForDisplay($date)
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


    public function getBranchByText(int $companyId, string $text): ?array
    {
        return $this->findBestTextMatch(
            $this->getBranchesByCompany($companyId),
            $text,
            static fn (array $branch): string => (string) ($branch['name'] ?? '')
        );
    }

    public function getServiceByText(int $branchId, string $text): ?array
    {
        return $this->findBestTextMatch(
            $this->getServicesByBranch($branchId),
            $text,
            static fn (array $service): string => (string) ($service['name'] ?? '')
        );
    }

    public function getAvailableBarberByText(int $branchId, string $date, int $productId, string $text): ?array
    {
        return $this->findBestTextMatch(
            $this->getAvailableBarbers($branchId, $date, $productId),
            $text,
            static fn (array $barber): string => (string) ($barber['name'] ?? '')
        );
    }

    public function getSlotByTimeText(int $barberId, int $branchId, string $date, int $productId, string $text): ?array
    {
        $slotGroups = $this->getAvailableSlots($barberId, $branchId, $date, $productId);
        $slots = $this->flattenSlotGroups($slotGroups);
        $candidates = $this->normalizeTimeCandidates($text);

        if ($candidates === []) {
            return null;
        }

        $matches = [];

        foreach ($slots as $slot) {
            $slotStart = $this->extractSlotStartTime((string) ($slot['time'] ?? ''));

            if ($slotStart === null) {
                continue;
            }

            if (in_array($slotStart, $candidates, true)) {
                $matches[] = $slot;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function findBestTextMatch(array $items, string $text, callable $labelExtractor): ?array
    {
        $needle = $this->normalizeSearchText($text);

        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestScore = 0;
        $secondBestScore = 0;

        foreach ($items as $item) {
            $label = $this->normalizeSearchText((string) $labelExtractor($item));

            if ($label === '') {
                continue;
            }

            $score = $this->scoreTextMatch($needle, $label);

            if ($score > $bestScore) {
                $secondBestScore = $bestScore;
                $bestScore = $score;
                $best = $item;
            } elseif ($score > $secondBestScore) {
                $secondBestScore = $score;
            }
        }

        if ($best === null || $bestScore < 65) {
            return null;
        }

        if ($secondBestScore > 0 && ($bestScore - $secondBestScore) < 10) {
            return null;
        }

        return $best;
    }

    private function scoreTextMatch(string $needle, string $label): int
    {
        if ($needle === $label) {
            return 100;
        }

        if (str_contains($needle, $label) || str_contains($label, $needle)) {
            return 90;
        }

        $needleTokens = array_values(array_filter(explode(' ', $needle)));
        $labelTokens = array_values(array_filter(explode(' ', $label)));

        if ($needleTokens === [] || $labelTokens === []) {
            return 0;
        }

        $matches = 0;

        foreach ($labelTokens as $labelToken) {
            foreach ($needleTokens as $needleToken) {
                if ($labelToken === $needleToken || str_contains($labelToken, $needleToken) || str_contains($needleToken, $labelToken)) {
                    $matches++;
                    break;
                }
            }
        }

        return (int) floor(($matches / max(1, count($labelTokens))) * 100);
    }

    private function normalizeSearchText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTimeCandidates(string $text): array
    {
        $normalized = $this->normalizeSearchText($text);

        if (!preg_match('/(?:a\s+las|alas|a\s+la|la)?\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/u', $normalized, $matches)) {
            return [];
        }

        $hour = (int) $matches[1];
        $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
        $meridiem = $matches[3] ?? null;

        if ($hour < 1 || $hour > 23 || $minute < 0 || $minute > 59) {
            return [];
        }

        if ($meridiem === 'pm') {
            return [sprintf('%02d:%02d', $hour < 12 ? $hour + 12 : $hour, $minute)];
        }

        if ($meridiem === 'am') {
            return [sprintf('%02d:%02d', $hour === 12 ? 0 : $hour, $minute)];
        }

        if ($hour >= 13) {
            return [sprintf('%02d:%02d', $hour, $minute)];
        }

        $candidates = [sprintf('%02d:%02d', $hour, $minute)];

        if ($hour >= 1 && $hour <= 11) {
            $candidates[] = sprintf('%02d:%02d', $hour + 12, $minute);
        }

        return array_values(array_unique($candidates));
    }

    private function extractSlotStartTime(string $slotLabel): ?string
    {
        $start = trim(explode(' - ', $slotLabel)[0] ?? $slotLabel);
        $dateTime = \DateTimeImmutable::createFromFormat('h:i A', $start, new \DateTimeZone(self::BOOKING_TIMEZONE));

        if (!$dateTime) {
            $dateTime = \DateTimeImmutable::createFromFormat('H:i', $start, new \DateTimeZone(self::BOOKING_TIMEZONE));
        }

        return $dateTime ? $dateTime->format('H:i') : null;
    }

    private function formatDateForDisplay(string $date): string
    {
        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d', $date, new \DateTimeZone(self::BOOKING_TIMEZONE));

        return $dateTime instanceof \DateTimeImmutable ? $dateTime->format('d/m/Y') : $date;
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

    private function getOccupiedRanges(int $barberId, int $branchId, string $date, int $turnDuration): array
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
                    'end' => $start->modify(sprintf('+%d minutes', $turnDuration)),
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
                    'end' => $start->modify(sprintf('+%d minutes', $duration > 0 ? $duration : $turnDuration)),
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
        int $slotMinutes,
        int $turnDuration,
        array $occupiedRanges
    ): array {
        $slots = [];

        $timezone = new \DateTimeZone(self::BOOKING_TIMEZONE);

        $currentTime = new \DateTimeImmutable(sprintf('%s %s', $date, $openTime), $timezone);
        $endTime = new \DateTimeImmutable(sprintf('%s %s', $date, $closeTime), $timezone);
        $now = new \DateTimeImmutable('now', $timezone);

        while ($currentTime < $endTime) {
            $slotStart = $currentTime;
            $slotEnd = $currentTime->modify(sprintf('+%d minutes', $turnDuration));

            if ($slotEnd > $endTime) {
                break;
            }

            /*
             * Si la fecha es hoy, no mostramos horarios que ya pasaron.
             */
            if ($slotStart <= $now) {
                $currentTime = $currentTime->modify(sprintf('+%d minutes', $turnDuration));
                continue;
            }

            if (!$this->hasOverlap($slotStart, $slotEnd, $occupiedRanges)) {
                $slots[] = $slotStart->format('h:i A') . ' - ' . $slotEnd->format('h:i A');
            }

            /*
             * El siguiente inicio avanza por duración completa del turno/servicio.
             * Esto evita mostrar bloques encimados visualmente como 11:00-12:00 y 11:30-12:30.
             */
            $currentTime = $currentTime->modify(sprintf('+%d minutes', $turnDuration));
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

    public function getProductRecommendationFromConfig(string $message): ?string
    {
        $config = $this->loadWhatsappResponsesConfig();
        $recommendations = $config['product_recommendations'] ?? [];

        if (!is_array($recommendations)) {
            return null;
        }

        $text = $this->normalizeConfigText($message);

        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }

            $keywords = $recommendation['keywords'] ?? [];

            if (!is_array($keywords)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (!is_string($keyword) || trim($keyword) === '') {
                    continue;
                }

                if (str_contains($text, $this->normalizeConfigText($keyword))) {
                    $response = $recommendation['response'] ?? null;

                    return is_string($response) && trim($response) !== '' ? $this->renderConfigText($response) : null;
                }
            }
        }

        return null;
    }

    public function getHaircutRecommendationFromConfig(string $message): ?string
    {
        $config = $this->loadWhatsappResponsesConfig();
        $recommendations = $config['haircut_recommendations'] ?? [];

        if (!is_array($recommendations)) {
            return null;
        }

        $text = $this->normalizeConfigText($message);

        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }

            $keywords = $recommendation['keywords'] ?? [];

            if (!is_array($keywords)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (!is_string($keyword) || trim($keyword) === '') {
                    continue;
                }

                if (str_contains($text, $this->normalizeConfigText($keyword))) {
                    $response = $recommendation['response'] ?? null;

                    return is_string($response) && trim($response) !== '' ? $this->renderConfigText($response) : null;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadWhatsappResponsesConfig(): array
    {
        $path = dirname(__DIR__, 3) . '/config/whatsapp/whatsapp_responses.json';

        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);

        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    private function renderConfigText(string $text): string
    {
        return str_replace('\n', "\n", $text);
    }

    private function normalizeConfigText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-z0-9:\/\-\s#]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }


}
