<?php

namespace App\Service\Appointment;

use App\Entity\Appointment;
use App\Entity\AppointmentService;
use App\Entity\BarberProfile;
use App\Entity\BarberSchedule;
use App\Entity\BarberService;
use App\Entity\BarberTimeOff;
use App\Entity\Branch;
use App\Entity\Customer;
use App\Entity\MasterProduct;
use App\Entity\Sale;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Enum\SaleStatus;
use App\Repository\AppointmentServiceRepository;
use App\Repository\CustomerRepository;
use App\Service\WhatsApp\WhatsAppClient;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AppointmentBookingService
{
    private const SOURCE_WHATSAPP = 'whatsapp';
    private const SOURCE_LANDING = 'landing';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerRepository $customerRepository,
        private readonly AppointmentServiceRepository $appointmentServiceRepository,
        private readonly WhatsAppClient $whatsAppClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Crea una cita usando el mismo contrato que consume:
     * POST /api/appointments/book
     *
     * @param array<string, mixed> $data
     *
     * @return array{
     *     appointmentId:int|null,
     *     customer:string|null
     * }
     */
    public function book(array $data): array
    {
        $this->validateBookingPayload($data);

        $this->entityManager->beginTransaction();

        try {
            $branchId = (int) $data['branch']['id'];

            /** @var Branch|null $branch */
            $branch = $this->entityManager
                ->getRepository(Branch::class)
                ->find($branchId);

            if (!$branch) {
                throw new \RuntimeException('Sucursal no encontrada: ' . $branchId);
            }

            $customer = $this->findOrCreateCustomer($data['customer'], $branch);

            $appointment = new Appointment();
            $appointment->setCustomer($customer);
            $appointment->setBranch($branch);
            $appointment->setTotalAmount((string) $data['summary']['totalAmount']);
            $appointment->setCurrency((string) $data['summary']['currency']);
            $appointment->setStatus(AppointmentStatus::PENDING);

            $this->entityManager->persist($appointment);

            foreach ($data['services'] as $serviceData) {
                $this->createAppointmentService(
                    appointment: $appointment,
                    branchId: $branchId,
                    serviceData: $serviceData
                );
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $result = [
                'appointmentId' => $appointment->getId(),
                'customer' => $customer->getName(),
            ];

            $this->notifyCustomerByWhatsAppIfNeeded($data, $appointment, $customer, $branch);

            return $result;
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();

            throw $exception;
        }
    }

    /**
     * Construye el payload de reserva desde el estado conversacional de WhatsApp
     * y crea la cita con la misma lógica de book().
     *
     * @param array<string, mixed> $state
     *
     * @return array{
     *     appointmentId:int|null,
     *     customer:string|null
     * }
     */
    public function bookFromWhatsAppState(array $state): array
    {
        $this->validateWhatsAppState($state);

        $payload = [
            'source' => self::SOURCE_WHATSAPP,
            'notifyCustomerWhatsApp' => false,
            'branch' => [
                'id' => (int) $state['branch_id'],
                'name' => (string) $state['branch_name'],
                'address' => (string) ($state['branch_address'] ?? ''),
            ],
            'customer' => [
                'name' => (string) $state['customer_name'],
                'email' => (string) $state['customer_email'],
                'phone' => (string) $state['customer_phone'],
                'countryCode' => (string) ($state['customer_country_code'] ?? '+52'),
                'notes' => (string) ($state['customer_notes'] ?? ''),
            ],
            'services' => [
                [
                    'cartItemId' => $this->generateCartItemId(),
                    'serviceId' => (int) $state['service_id'],
                    'name' => (string) $state['service_name'],
                    'price' => (float) $state['service_price'],
                    'duration' => (int) $state['service_duration'],
                    'professionalId' => (int) $state['barber_id'],
                    'professionalName' => (string) $state['barber_name'],
                    'time' => (string) $state['time_label'],
                    'scheduledDate' => (string) $state['date'],
                    'scheduledDateTime' => (string) $state['scheduled_date_time'],
                ],
            ],
            'summary' => [
                'servicesCount' => 1,
                'totalAmount' => (float) $state['service_price'],
                'currency' => 'MXN',
            ],
            'scheduling' => [
                'dateISO' => $this->buildDateIso((string) $state['date']),
                'timezone' => 'America/Mexico_City',
            ],
        ];

        return $this->book($payload);
    }

    /**
     * @param array<string, mixed> $customerData
     */
    private function findOrCreateCustomer(array $customerData, Branch $branch): Customer
    {
        $email = mb_strtolower(trim((string) ($customerData['email'] ?? '')));
        $phone = preg_replace('/\D+/', '', (string) ($customerData['phone'] ?? '')) ?? '';

        /** @var Customer|null $customer */
        $customer = $this->customerRepository->createQueryBuilder('c')
            ->where('LOWER(c.email) = :email')
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($customer) {
            $customer->setName(trim((string) $customerData['name']));
            $customer->setPhone($phone);
            $customer->setCountryCode((string) ($customerData['countryCode'] ?? '+52'));
            $customer->setBranch($branch);

            return $customer;
        }

        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setName(trim((string) $customerData['name']));
        $customer->setPhone($phone);
        $customer->setCountryCode((string) ($customerData['countryCode'] ?? '+52'));
        $customer->setNotes((string) ($customerData['notes'] ?? ''));
        $customer->setBranch($branch);

        $this->entityManager->persist($customer);

        return $customer;
    }

    /**
     * @param array<string, mixed> $serviceData
     */
    private function createAppointmentService(
        Appointment $appointment,
        int $branchId,
        array $serviceData
    ): void {
        $professionalId = $serviceData['professionalId'] ?? null;
        $serviceId = (int) ($serviceData['serviceId'] ?? 0);

        /** @var MasterProduct|null $masterProduct */
        $masterProduct = $this->entityManager
            ->getRepository(MasterProduct::class)
            ->find($serviceId);

        if (!$masterProduct) {
            throw new \RuntimeException('Servicio no encontrado: ' . $serviceId);
        }

        $scheduledDateTimeStr = (string) ($serviceData['scheduledDateTime'] ?? '');
        $cleanDateTimeStr = explode(' - ', $scheduledDateTimeStr)[0];

        $scheduledDateTime = \DateTime::createFromFormat('Y-m-d h:i A', $cleanDateTimeStr);

        if (!$scheduledDateTime) {
            throw new \RuntimeException('Formato de fecha inválido: ' . $scheduledDateTimeStr);
        }

        $duration = (int) ($serviceData['duration'] ?? 0);

        if ($duration <= 0) {
            throw new \RuntimeException('Duración inválida para el servicio: ' . $masterProduct->getName());
        }

        if ($professionalId === 'any') {
            $barber = $this->findAvailableBarber(
                branchId: $branchId,
                scheduledDateTime: $scheduledDateTime,
                duration: $duration,
                productId: $serviceId
            );

            if (!$barber) {
                throw new \RuntimeException(sprintf(
                    'No hay barberos disponibles para el servicio %s en el horario %s',
                    $masterProduct->getName(),
                    $scheduledDateTimeStr
                ));
            }
        } else {
            $barber = $this->lockBarberProfileByUserId((int) $professionalId);

            if (!$barber) {
                throw new \RuntimeException('Barbero no encontrado para el usuario: ' . $professionalId);
            }

            if ($this->appointmentServiceRepository->hasOverlap($barber->getId(), $scheduledDateTime, $duration)) {
                throw new \RuntimeException(sprintf(
                    'El barbero %s ya tiene una cita programada en el horario %s que se traslapa con esta solicitud.',
                    (string) ($serviceData['professionalName'] ?? 'seleccionado'),
                    $scheduledDateTimeStr
                ));
            }
        }

        $appointmentService = new AppointmentService();
        $appointmentService->setAppointment($appointment);
        $appointmentService->setService($masterProduct);
        $appointmentService->setBarber($barber);
        $appointmentService->setScheduledDateTime($scheduledDateTime);
        $appointmentService->setDuration($duration);
        $appointmentService->setPrice((string) ($serviceData['price'] ?? '0'));
        $appointmentService->setCartItemId((string) ($serviceData['cartItemId'] ?? $this->generateCartItemId()));

        $this->entityManager->persist($appointmentService);
    }

    private function lockBarberProfileByUserId(int $userId): ?BarberProfile
    {
        /** @var BarberProfile|null $barber */
        $barber = $this->entityManager->createQueryBuilder()
            ->select('bp')
            ->from(BarberProfile::class, 'bp')
            ->where('bp.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $barber;
    }

    private function findAvailableBarber(
        int $branchId,
        \DateTime $scheduledDateTime,
        int $duration,
        int $productId
    ): ?BarberProfile {
        $dayOfWeek = (int) $scheduledDateTime->format('N');
        $shiftStart = clone $scheduledDateTime;
        $shiftEnd = (clone $scheduledDateTime)->modify("+{$duration} minutes");

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('bp')
            ->from(BarberProfile::class, 'bp')
            ->join(BarberService::class, 'bserv', 'WITH', 'bserv.barber = bp.user')
            ->join(BarberSchedule::class, 'bsched', 'WITH', 'bsched.barber = bp.user')
            ->join(User::class, 'u', 'WITH', 'u.id = bp.user')
            ->where('u.enabled = true AND u.barberSn = true')
            ->andWhere('bserv.product = :productId AND bserv.isActive = true')
            ->andWhere('bsched.branch = :branchId AND bsched.dayOfWeek = :dayOfWeek')
            ->andWhere('bsched.validFrom <= :date AND (bsched.validTo IS NULL OR bsched.validTo >= :date)')
            ->setParameter('productId', $productId)
            ->setParameter('branchId', $branchId)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('date', $scheduledDateTime->format('Y-m-d'));

        /** @var BarberProfile[] $potentialBarbers */
        $potentialBarbers = $queryBuilder->getQuery()->getResult();

        foreach ($potentialBarbers as $barberProfile) {
            $barberUserId = $barberProfile->getUser()->getId();

            if ($this->appointmentServiceRepository->hasOverlap($barberProfile->getId(), $scheduledDateTime, $duration)) {
                continue;
            }

            if ($this->hasTimeOffOverlap($barberUserId, $branchId, $shiftStart, $shiftEnd)) {
                continue;
            }

            if ($this->hasSaleOverlap($barberUserId, $scheduledDateTime, $shiftStart, $shiftEnd)) {
                continue;
            }

            $lockedBarberProfile = $this->lockBarberProfileByProfileId($barberProfile->getId());

            if ($lockedBarberProfile) {
                return $lockedBarberProfile;
            }
        }

        return null;
    }

    private function lockBarberProfileByProfileId(int $barberProfileId): ?BarberProfile
    {
        /** @var BarberProfile|null $barber */
        $barber = $this->entityManager->createQueryBuilder()
            ->select('bp')
            ->from(BarberProfile::class, 'bp')
            ->where('bp.id = :id')
            ->setParameter('id', $barberProfileId)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $barber;
    }

    private function hasTimeOffOverlap(
        int $barberUserId,
        int $branchId,
        \DateTime $shiftStart,
        \DateTime $shiftEnd
    ): bool {
        $timeOffOverlap = $this->entityManager
            ->getRepository(BarberTimeOff::class)
            ->createQueryBuilder('to')
            ->where('to.barber = :barberId')
            ->andWhere('to.branch = :branchId OR to.branch IS NULL')
            ->andWhere(':shiftStart < to.endAtLocal AND :shiftEnd > to.startAtLocal')
            ->setParameter('barberId', $barberUserId)
            ->setParameter('branchId', $branchId)
            ->setParameter('shiftStart', $shiftStart)
            ->setParameter('shiftEnd', $shiftEnd)
            ->getQuery()
            ->getResult();

        return !empty($timeOffOverlap);
    }

    private function hasSaleOverlap(
        int $barberUserId,
        \DateTime $scheduledDateTime,
        \DateTime $shiftStart,
        \DateTime $shiftEnd
    ): bool {
        /** @var Sale[] $sales */
        $sales = $this->entityManager
            ->getRepository(Sale::class)
            ->createQueryBuilder('s')
            ->join('s.details', 'd')
            ->where('d.serviceProvider = :barberId')
            ->andWhere('s.status != :cancelled')
            ->andWhere('s.saleDate >= :startOfDay AND s.saleDate <= :endOfDay')
            ->setParameter('barberId', $barberUserId)
            ->setParameter('startOfDay', $scheduledDateTime->format('Y-m-d 00:00:00'))
            ->setParameter('endOfDay', $scheduledDateTime->format('Y-m-d 23:59:59'))
            ->setParameter('cancelled', SaleStatus::CANCELLED->value)
            ->getQuery()
            ->getResult();

        foreach ($sales as $sale) {
            $saleStart = clone $sale->getSaleDate();
            $saleEnd = (clone $saleStart)->modify('+60 minutes');

            $maxStart = max($shiftStart->getTimestamp(), $saleStart->getTimestamp());
            $minEnd = min($shiftEnd->getTimestamp(), $saleEnd->getTimestamp());

            if ($maxStart < $minEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function notifyCustomerByWhatsAppIfNeeded(
        array $data,
        Appointment $appointment,
        Customer $customer,
        Branch $branch
    ): void {
        $source = mb_strtolower((string) ($data['source'] ?? self::SOURCE_LANDING));
        $shouldNotify = (bool) ($data['notifyCustomerWhatsApp'] ?? true);

        if ($source === self::SOURCE_WHATSAPP || !$shouldNotify) {
            return;
        }

        $phone = trim((string) ($data['customer']['phone'] ?? $customer->getPhone() ?? ''));

        if ($phone === '') {
            $this->logger->warning('No se envió confirmación WhatsApp: cliente sin teléfono.', [
                'appointment_id' => $appointment->getId(),
                'customer_id' => $customer->getId(),
            ]);

            return;
        }

        $customerName = trim((string) ($data['customer']['name'] ?? $customer->getName() ?? 'Cliente'));
        $appointmentDetails = $this->formatBookingConfirmationDetails(
            data: $data,
            appointment: $appointment,
            branch: $branch
        );

        try {
            $result = $this->whatsAppClient->sendBookingConfirmationTemplate(
                to: $phone,
                customerName: $customerName,
                appointmentDetails: $appointmentDetails
            );

            $this->logger->info('Confirmación de cita enviada por WhatsApp.', [
                'appointment_id' => $appointment->getId(),
                'customer_phone' => $phone,
                'result' => $result,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('No se pudo enviar confirmación de cita por WhatsApp.', [
                'appointment_id' => $appointment->getId(),
                'customer_phone' => $phone,
                'detail' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatBookingConfirmationDetails(
        array $data,
        Appointment $appointment,
        Branch $branch
    ): string {
        $serviceData = $data['services'][0] ?? [];

        $serviceName = (string) ($serviceData['name'] ?? 'Servicio');
        $barberName = (string) ($serviceData['professionalName'] ?? 'Por asignar');
        $scheduledDate = (string) ($serviceData['scheduledDate'] ?? '');
        $timeLabel = (string) ($serviceData['time'] ?? '');

        if ($scheduledDate !== '') {
            try {
                $date = new \DateTimeImmutable($scheduledDate);
                $scheduledDate = $date->format('d/m/Y');
            } catch (\Throwable) {
                // Conserva la fecha original si no puede formatearse.
            }
        }
        
        return sprintf(
            "Folio: %s | Sucursal: %s | Servicio: %s | Barbero: %s | Fecha: %s | Horario: %s",
            (string) ($appointment->getId() ?? ''),
            (string) ($data['branch']['name'] ?? $branch->getName()),
            $serviceName,
            $barberName,
            $scheduledDate,
            $timeLabel
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateBookingPayload(array $data): void
    {
        if (empty($data['branch']['id'])) {
            throw new \InvalidArgumentException('Falta la sucursal de la reserva.');
        }

        if (empty($data['customer']) || !is_array($data['customer'])) {
            throw new \InvalidArgumentException('Faltan los datos del cliente.');
        }

        if (empty($data['customer']['name'])) {
            throw new \InvalidArgumentException('Falta el nombre del cliente.');
        }

        if (empty($data['customer']['email']) || !filter_var((string) $data['customer']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo del cliente no es válido.');
        }

        if (empty($data['customer']['phone'])) {
            throw new \InvalidArgumentException('Falta el teléfono del cliente.');
        }

        if (empty($data['services']) || !is_array($data['services'])) {
            throw new \InvalidArgumentException('La reserva debe incluir al menos un servicio.');
        }

        if (empty($data['summary']['totalAmount'])) {
            throw new \InvalidArgumentException('Falta el total de la reserva.');
        }

        if (empty($data['summary']['currency'])) {
            throw new \InvalidArgumentException('Falta la moneda de la reserva.');
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function validateWhatsAppState(array $state): void
    {
        $requiredFields = [
            'branch_id',
            'branch_name',
            'service_id',
            'service_name',
            'service_price',
            'service_duration',
            'barber_id',
            'barber_name',
            'date',
            'time_label',
            'scheduled_date_time',
            'customer_name',
            'customer_email',
            'customer_phone',
        ];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $state) || $state[$field] === null || $state[$field] === '') {
                throw new \InvalidArgumentException('Falta el dato requerido para crear la cita: ' . $field);
            }
        }

        if (!filter_var((string) $state['customer_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo del cliente no es válido.');
        }
    }

    private function generateCartItemId(): string
    {
        return (string) ((int) round(microtime(true) * 1000));
    }

    private function buildDateIso(string $date): string
    {
        $dateTime = new \DateTimeImmutable(
            $date . ' 00:00:00',
            new \DateTimeZone('America/Mexico_City')
        );

        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.000\Z');
    }
}
