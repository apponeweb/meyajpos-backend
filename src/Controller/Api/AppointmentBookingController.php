<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\AppointmentService;
use App\Entity\BarberProfile;
use App\Entity\Branch;
use App\Entity\Customer;
use App\Entity\MasterProduct;
use App\Enum\AppointmentStatus;
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
            // 1. Manejar Cliente
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
            
            $this->entityManager->persist($customer);

            // 2. Manejar Sucursal
            $branchId = $data['branch']['id'];
            $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
            
            if (!$branch) {
                throw new \Exception("Sucursal no encontrada: " . $branchId);
            }

            // 3. Crear Cita (Appointment)
            $appointment = new Appointment();
            $appointment->setCustomer($customer);
            $appointment->setBranch($branch);
            $appointment->setTotalAmount((string)$data['summary']['totalAmount']);
            $appointment->setCurrency($data['summary']['currency']);
            $appointment->setStatus(AppointmentStatus::CONFIRMED);
            
            $this->entityManager->persist($appointment);

            // 4. Procesar Servicios y Manejar Concurrencia
            foreach ($data['services'] as $serviceData) {
                $userId = $serviceData['professionalId'];
                
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

                $scheduledDateTimeStr = $serviceData['scheduledDateTime'];
                $scheduledDateTime = \DateTime::createFromFormat('Y-m-d h:i A', $scheduledDateTimeStr);
                
                if (!$scheduledDateTime) {
                    throw new \Exception("Formato de fecha inválido: " . $scheduledDateTimeStr);
                }

                $duration = (int)$serviceData['duration'];

                // Verificar traslape
                if ($this->appointmentServiceRepository->hasOverlap($barber->getId(), $scheduledDateTime, $duration)) {
                    throw new \Exception(sprintf(
                        "El barbero %s ya tiene una cita programada en el horario %s que se traslapa con esta solicitud.",
                        $serviceData['professionalName'],
                        $scheduledDateTimeStr
                    ));
                }

                $serviceId = $serviceData['serviceId'];
                $masterProduct = $this->entityManager->getRepository(MasterProduct::class)->find($serviceId);
                
                if (!$masterProduct) {
                    throw new \Exception("Servicio no encontrado: " . $serviceId);
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
}
