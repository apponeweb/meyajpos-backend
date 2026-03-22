<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\AppointmentService;
use App\Entity\BarberProfile;
use App\Entity\Branch;
use App\Entity\Customer;
use App\Entity\MasterProduct;
use App\Enum\AppointmentStatus;
use App\Enum\SaleStatus;
use App\Entity\BarberService;
use App\Entity\BarberSchedule;
use App\Entity\User;
use App\Entity\BarberTimeOff;
use App\Entity\Sale;
use App\Repository\CustomerRepository;
use App\Repository\AppointmentServiceRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/appointments')]
class AppointmentBookingController extends BaseController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        private CustomerRepository $customerRepository,
        private AppointmentServiceRepository $appointmentServiceRepository
    ) {
        parent::__construct($entityManager, $security);
    }

    protected function getEntityClass(): string
    {
        return Appointment::class;
    }

    protected function getFormTypeClass(): string
    {
        // Not using a standard Symfony form for this complex multi-entity booking
        return '';
    }

    #[Route('/book', name: 'api_appointment_book', methods: ['POST'])]
    public function book(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->beginTransaction();

        try {
            // 1. Manejar Sucursal
            $branchId = $data['branch']['id'];
            $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
            
            if (!$branch) {
                throw new \Exception("Sucursal no encontrada: " . $branchId);
            }

            // 2. Manejar Cliente
            $customerData = $data['customer'];
            $customer = $this->customerRepository->findOneBy(['email' => $customerData['email']]);
            
            if (!$customer) {
                $customer = new Customer();
                $customer->setEmail($customerData['email']);
            }
            
            $customer->setName($customerData['name']);
            $customer->setPhone($customerData['phone']);
            $customer->setCountryCode($customerData['countryCode']);
            $customer->setNotes($customerData['notes']);
            $customer->setBranch($branch);
            
            $this->entityManager->persist($customer);

            // 3. Crear Cita (Appointment)
            $appointment = new Appointment();
            $appointment->setCustomer($customer);
            $appointment->setBranch($branch);
            $appointment->setTotalAmount((string)$data['summary']['totalAmount']);
            $appointment->setCurrency($data['summary']['currency']);
            $appointment->setStatus(AppointmentStatus::PENDING);
            
            $this->entityManager->persist($appointment);

            // 4. Procesar Servicios y Manejar Concurrencia
            foreach ($data['services'] as $serviceData) {
                $userId = $serviceData['professionalId'];
                
                $serviceId = $serviceData['serviceId'];
                $masterProduct = $this->entityManager->getRepository(MasterProduct::class)->find($serviceId);
                
                if (!$masterProduct) {
                    throw new \Exception("Servicio no encontrado: " . $serviceId);
                }
                
                $scheduledDateTimeStr = $serviceData['scheduledDateTime'];
                $scheduledDateTime = \DateTime::createFromFormat('Y-m-d h:i A', $scheduledDateTimeStr);
                
                if (!$scheduledDateTime) {
                    throw new \Exception("Formato de fecha inválido: " . $scheduledDateTimeStr);
                }

                $duration = (int)$serviceData['duration'];
                
                if ($userId === 'any') {
                    $barber = $this->findAvailableBarber((int)$branchId, $scheduledDateTime, $duration, (int)$serviceId);
                    if (!$barber) {
                        throw new \Exception("No hay barberos disponibles para el servicio " . $masterProduct->getName() . " en el horario " . $scheduledDateTimeStr);
                    }
                } else {
                    // --- BLOQUEO PESIMISTA ---
                    // Bloqueamos al barbero para evitar que otras transacciones verifiquen disponibilidad simultáneamente
                    $barber = $this->entityManager->createQueryBuilder()
                        ->select('bp')
                        ->from(BarberProfile::class, 'bp')
                        ->where('bp.user = :userId')
                        ->setParameter('userId', $userId)
                        ->getQuery()
                        ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                        ->getOneOrNullResult();
                    
                    if (!$barber) {
                        throw new \Exception("Barbero no encontrado para el usuario: " . $userId);
                    }

                    // Verificar traslape Manual
                    if ($this->appointmentServiceRepository->hasOverlap($barber->getId(), $scheduledDateTime, $duration)) {
                        throw new \Exception(sprintf(
                            "El barbero %s ya tiene una cita programada en el horario %s que se traslapa con esta solicitud.",
                            $serviceData['professionalName'] ?? 'seleccionado',
                            $scheduledDateTimeStr
                        ));
                    }
                }

                $appService = new AppointmentService();
                $appService->setAppointment($appointment);
                $appService->setService($masterProduct);
                $appService->setBarber($barber);
                $appService->setScheduledDateTime($scheduledDateTime);
                $appService->setDuration($duration);
                $appService->setPrice((string)$serviceData['price']);
                $appService->setCartItemId((string)$serviceData['cartItemId']);
                
                $this->entityManager->persist($appService);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $this->json([
                'message' => 'Cita reservada con éxito',
                'data' => [
                    'appointmentId' => $appointment->getId(),
                    'customer' => $customer->getName()
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return $this->json([
                'message' => 'Error al realizar la reserva',
                'detail' => $e->getMessage()
            ], Response::HTTP_CONFLICT); // HTTP 409 Conflict para traslapes o errores de negocio
        }
    }

    private function findAvailableBarber(int $branchId, \DateTime $scheduledDateTime, int $duration, int $productId): ?BarberProfile
    {
        $dayOfWeek = (int)$scheduledDateTime->format('N');
        $shiftStart = clone $scheduledDateTime;
        $shiftEnd = (clone $scheduledDateTime)->modify("+{$duration} minutes");

        // 1. Get potential barbers
        $qb = $this->entityManager->createQueryBuilder()
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

        $potentialBarbers = $qb->getQuery()->getResult();

        foreach ($potentialBarbers as $barberProfile) {
            $barberId = $barberProfile->getUser()->getId();

            // First check basic AppointmentService overlap
            if ($this->appointmentServiceRepository->hasOverlap($barberProfile->getId(), $scheduledDateTime, $duration)) {
                continue;
            }

            // Check TimeOff Overlap
            $timeOffOverlap = $this->entityManager->getRepository(BarberTimeOff::class)
                ->createQueryBuilder('to')
                ->where('to.barber = :barberId')
                ->andWhere('to.branch = :branchId OR to.branch IS NULL')
                ->andWhere(':shiftStart < to.endAtLocal AND :shiftEnd > to.startAtLocal')
                ->setParameter('barberId', $barberId)
                ->setParameter('branchId', $branchId)
                ->setParameter('shiftStart', $shiftStart)
                ->setParameter('shiftEnd', $shiftEnd)
                ->getQuery()
                ->getResult();

            if (!empty($timeOffOverlap)) {
                continue;
            }

            // Check Sale overlap
            $sales = $this->entityManager->getRepository(Sale::class)
                ->createQueryBuilder('s')
                ->join('s.details', 'd')
                ->where('d.serviceProvider = :barberId')
                ->andWhere('s.status != :cancelled')
                ->andWhere('s.saleDate >= :startOfDay AND s.saleDate <= :endOfDay')
                ->setParameter('barberId', $barberId)
                ->setParameter('startOfDay', $scheduledDateTime->format('Y-m-d 00:00:00'))
                ->setParameter('endOfDay', $scheduledDateTime->format('Y-m-d 23:59:59'))
                ->setParameter('cancelled', SaleStatus::CANCELLED->value)
                ->getQuery()
                ->getResult();

            $isOccupiedBySale = false;
            foreach ($sales as $sale) {
                // Approximate 60 minutes for sales
                $saleStart = clone $sale->getSaleDate();
                $saleEnd = (clone $saleStart)->modify("+60 minutes");
                
                $maxStart = max($shiftStart->getTimestamp(), $saleStart->getTimestamp());
                $minEnd = min($shiftEnd->getTimestamp(), $saleEnd->getTimestamp());
                
                if ($maxStart < $minEnd) {
                    $isOccupiedBySale = true;
                    break;
                }
            }

            if ($isOccupiedBySale) {
                continue;
            }

            // If we've made it here, lock this barber pesimistically and return it!
            $lockedBarberProfile = $this->entityManager->createQueryBuilder()
                ->select('bp')
                ->from(BarberProfile::class, 'bp')
                ->where('bp.id = :id')
                ->setParameter('id', $barberProfile->getId())
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();

            if ($lockedBarberProfile) {
                return $lockedBarberProfile;
            }
        }
        
        return null;
    }
}
