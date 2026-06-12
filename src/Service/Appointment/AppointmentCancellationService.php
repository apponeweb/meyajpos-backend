<?php

namespace App\Service\Appointment;

use App\Enum\AppointmentStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class AppointmentCancellationService
{
    private const BOOKING_TIMEZONE = 'America/Mexico_City';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function findCancelableAppointmentByFolio(int $appointmentId): ?array
    {
        $appointment = $this->connection->fetchAssociative(
            <<<SQL
            SELECT
                a.id AS appointment_id,
                a.status,
                c.name AS customer_name,
                c.email AS customer_email,
                c.phone AS customer_phone,
                b.name AS branch_name,
                b.address AS branch_address,
                mp.name AS service_name,
                TRIM(CONCAT(u.name, ' ', COALESCE(u.last_name, ''))) AS barber_name,
                aps.scheduled_date_time,
                aps.duration,
                aps.price
            FROM tbd_appointment a
            INNER JOIN tbd_customer c ON c.id = a.customer_id
            INNER JOIN tbn_branch b ON b.id = a.branch_id
            INNER JOIN tbd_appointment_service aps ON aps.appointment_id = a.id
            INNER JOIN tbd_master_product mp ON mp.id = aps.service_id
            INNER JOIN tbd_barber_profile bp ON bp.id = aps.barber_id
            INNER JOIN public."user" u ON u.id = bp.barber_user_id
            WHERE a.id = :appointmentId
              AND a.status != :cancelledStatus
            ORDER BY aps.scheduled_date_time ASC
            LIMIT 1
            SQL,
            [
                'appointmentId' => $appointmentId,
                'cancelledStatus' => AppointmentStatus::CANCELLED->value,
            ],
            [
                'appointmentId' => ParameterType::INTEGER,
                'cancelledStatus' => ParameterType::INTEGER,
            ]
        );

        if ($appointment === false) {
            return null;
        }

        $scheduledDateTime = new \DateTimeImmutable(
            (string) $appointment['scheduled_date_time'],
            new \DateTimeZone(self::BOOKING_TIMEZONE)
        );

        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::BOOKING_TIMEZONE));

        if ($scheduledDateTime <= $now) {
            return null;
        }

        $duration = (int) ($appointment['duration'] ?? 0);

        if ($duration <= 0) {
            $duration = 60;
        }

        $endDateTime = $scheduledDateTime->modify(sprintf('+%d minutes', $duration));

        return [
            'appointment_id' => (int) $appointment['appointment_id'],
            'status' => (int) $appointment['status'],
            'customer_name' => (string) ($appointment['customer_name'] ?? ''),
            'customer_email' => (string) ($appointment['customer_email'] ?? ''),
            'customer_phone' => (string) ($appointment['customer_phone'] ?? ''),
            'branch_name' => (string) ($appointment['branch_name'] ?? ''),
            'branch_address' => (string) ($appointment['branch_address'] ?? ''),
            'service_name' => (string) ($appointment['service_name'] ?? ''),
            'barber_name' => trim((string) ($appointment['barber_name'] ?? '')),
            'scheduled_date' => $scheduledDateTime->format('Y-m-d'),
            'scheduled_date_display' => $scheduledDateTime->format('d/m/Y'),
            'scheduled_start' => $scheduledDateTime->format('h:i A'),
            'scheduled_end' => $endDateTime->format('h:i A'),
            'scheduled_time_label' => $scheduledDateTime->format('h:i A') . ' - ' . $endDateTime->format('h:i A'),
            'duration' => $duration,
            'price' => (float) ($appointment['price'] ?? 0),
        ];
    }

    public function cancelByFolio(int $appointmentId): array
    {
        $appointment = $this->findCancelableAppointmentByFolio($appointmentId);

        if ($appointment === null) {
            throw new \RuntimeException('No encontré una cita futura activa con ese folio.');
        }

        $affectedRows = $this->connection->executeStatement(
            <<<SQL
            UPDATE tbd_appointment
            SET
                status = :cancelledStatus,
                updated_at = NOW()
            WHERE id = :appointmentId
              AND status != :cancelledStatus
            SQL,
            [
                'appointmentId' => $appointmentId,
                'cancelledStatus' => AppointmentStatus::CANCELLED->value,
            ],
            [
                'appointmentId' => ParameterType::INTEGER,
                'cancelledStatus' => ParameterType::INTEGER,
            ]
        );

        if ($affectedRows < 1) {
            throw new \RuntimeException('La cita no pudo cancelarse o ya estaba cancelada.');
        }

        return $appointment;
    }

    public function formatAppointmentForConfirmation(array $appointment): string
    {
        return sprintf(
            "Encontré esta cita:\n\nFolio: *%s*\nSucursal: *%s*\nServicio: *%s*\nBarbero: *%s*\nFecha: *%s*\nHorario: *%s*\nCliente: *%s*\n\n¿Confirmas que deseas cancelarla?\n\nResponde *CANCELAR CITA* para confirmar o *NO* para salir.",
            (string) ($appointment['appointment_id'] ?? ''),
            (string) ($appointment['branch_name'] ?? ''),
            (string) ($appointment['service_name'] ?? ''),
            (string) ($appointment['barber_name'] ?? ''),
            (string) ($appointment['scheduled_date_display'] ?? ''),
            (string) ($appointment['scheduled_time_label'] ?? ''),
            (string) ($appointment['customer_name'] ?? '')
        );
    }
}
