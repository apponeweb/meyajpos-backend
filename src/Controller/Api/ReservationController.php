<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\AppointmentService;
use App\Entity\BarberProfile;
use App\Entity\BarberTimeOff;
use App\Entity\Branch;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Repository\AppointmentRepository;
use App\Repository\AppointmentServiceRepository;
use App\Repository\BarberTimeOffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\Tools\Pagination\Paginator;


#[Route('/reservation')]
class ReservationController extends BaseController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        private AppointmentRepository $appointmentRepository,
        private AppointmentServiceRepository $appointmentServiceRepository,
        private BarberTimeOffRepository $barberTimeOffRepository,
        private \App\Repository\AppointmentStatusConfigRepository $statusConfigRepository
    ) {
        parent::__construct($entityManager, $security);
    }

    protected function getEntityClass(): string
    {
        return Appointment::class;
    }

    protected function getFormTypeClass(): string
    {
        return '';
    }

    #[Route('/list', name: 'api_reservation_list', methods: ['GET'])]
    public function listReservations(Request $request): JsonResponse
    {
        $current = $request->query->get('current', 1);
        $pageSize = $request->query->get('pageSize', 10);
        $search = $request->query->get('search');
        $date = $request->query->get('date');
        $barberId = $request->query->get('barberId');

        $queryBuilder = $this->entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.customer', 'c')
            ->orderBy('a.createdAt', 'DESC');

        if ($date || $barberId) {
            $queryBuilder->leftJoin('a.services', 'srv');
            
            if ($barberId) {
                $queryBuilder->leftJoin('srv.barber', 'b')
                             ->andWhere('b.user = :barberId')
                             ->setParameter('barberId', $barberId);
            }
            
            if ($date) {
                if (str_contains($date, ',')) {
                    [$startStr, $endStr] = explode(',', $date);
                    $dateStart = new \DateTime($startStr . ' 00:00:00');
                    $dateEnd = new \DateTime($endStr . ' 23:59:59');
                } else {
                    $dateStart = new \DateTime($date . ' 00:00:00');
                    $dateEnd = new \DateTime($date . ' 23:59:59');
                }
                $queryBuilder->andWhere('srv.scheduledDateTime >= :dateStart')
                             ->andWhere('srv.scheduledDateTime <= :dateEnd')
                             ->setParameter('dateStart', $dateStart)
                             ->setParameter('dateEnd', $dateEnd);
            }
        }

        if ($search) {
            $queryBuilder->andWhere('c.name LIKE :search OR c.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Exclude cancelled appointments to match calendar behavior
        $queryBuilder->andWhere('a.status != :cancelled')
            ->setParameter('cancelled', AppointmentStatus::CANCELLED);

        $queryBuilder->setFirstResult(($current - 1) * $pageSize)
            ->setMaxResults($pageSize);

        $paginator = new Paginator($queryBuilder->getQuery(), true);
        $total = count($paginator);
        $appointments = $paginator->getIterator();

        $results = [];

        $configs = $this->getStatusConfigs();

        foreach ($appointments as $app) {
            $services = [];
            foreach ($app->getServices() as $s) {
                $services[] = [
                    'id' => $s->getId(),
                    'service' => $s->getService()->getName(),
                    'barber' => $s->getBarber()->getUser()->getName(),
                    'barberPhoto' => $s->getBarber()->getPhotoUrl(),
                    'date' => $s->getScheduledDateTime()->format('d/m/Y H:i:s'),
                    'price' => $s->getPrice()
                ];
            }

            $startTime = null;
            $endTime = null;
            if (count($app->getServices()) > 0) {
                $firstService = $app->getServices()[0];
                $startTime = $firstService->getScheduledDateTime();
                $lastService = $app->getServices()[count($app->getServices()) - 1];
                $endTime = clone $lastService->getScheduledDateTime();
                $endTime->modify('+' . $lastService->getDuration() . ' minutes');
            }

            $results[] = [
                'id' => $app->getId(),
                'customer' => $app->getCustomer()->getName(),
                'status' => $app->getStatus()->value,
                'statusLabel' => $configs[$app->getStatus()->value]['label'] ?? $app->getStatus()->getLabel(),
                'statusColor' => $configs[$app->getStatus()->value]['color'] ?? $this->getDefaultColor($app->getStatus()),
                'totalAmount' => $app->getTotalAmount(),
                'createdAt' => $app->getCreatedAt()->format('d/m/Y H:i:s'),
                'startTime' => $startTime ? $startTime->format('d/m/Y H:i:s') : null,
                'endTime' => $endTime ? $endTime->format('d/m/Y H:i:s') : null,
                'services' => $services
            ];
        }

        return $this->json([
            'results' => $results,
            'total' => $total
        ], Response::HTTP_OK);
    }

    #[Route('/calendar', name: 'api_reservation_calendar', methods: ['GET'])]
    public function getCalendarEvents(Request $request): JsonResponse
    {
        $startStr = $request->query->get('start'); // Format: 2026-03-01T00:00:00Z
        $endStr = $request->query->get('end');
        $barberId = $request->query->get('barberId');
        
        $start = new \DateTime($startStr);
        $end = new \DateTime($endStr);

        $configs = $this->getStatusConfigs();

        // 1. Fetch Appointment Services
        $servicesQuery = $this->appointmentServiceRepository->createQueryBuilder('s')
            ->join('s.appointment', 'a')
            ->where('s.scheduledDateTime >= :start')
            ->andWhere('s.scheduledDateTime <= :end')
            ->andWhere('a.status != :cancelled')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('cancelled', AppointmentStatus::CANCELLED);

        if ($barberId) {
            $servicesQuery->join('s.barber', 'b')
                          ->andWhere('b.user = :barberId')
                          ->setParameter('barberId', $barberId);
        }

        $services = $servicesQuery->getQuery()->getResult();

        $events = [];
        foreach ($services as $s) {
            $endAt = clone $s->getScheduledDateTime();
            $endAt->modify('+' . $s->getDuration() . ' minutes');

            $events[] = [
                'id' => 'service_' . $s->getId(),
                'resourceId' => $s->getBarber()->getUser()->getId(),
                'title' => $s->getAppointment()->getCustomer()->getName() . ' - ' . $s->getService()->getName(),
                'start' => $s->getScheduledDateTime()->format('Y-m-d\TH:i:s'),
                'end' => $endAt->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $configs[$s->getAppointment()->getStatus()->value]['color'] ?? $this->getDefaultColor($s->getAppointment()->getStatus()),
                'extendedProps' => [
                    'type' => 'appointment',
                    'serviceId' => $s->getId(),
                    'appointmentId' => $s->getAppointment()->getId(),
                    'status' => $s->getAppointment()->getStatus()->value,
                    'customer' => $s->getAppointment()->getCustomer()->getName(),
                    'serviceName' => $s->getService()->getName()
                ]
            ];
        }

        // 2. Fetch Blocks (TimeOff)
        $blocksQuery = $this->barberTimeOffRepository->createQueryBuilder('b')
            ->where('b.startAtLocal >= :start OR b.endAtLocal >= :start')
            ->andWhere('b.startAtLocal <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($barberId) {
            $blocksQuery->andWhere('b.barber = :barberId')
                        ->setParameter('barberId', $barberId);
        }

        $blocks = $blocksQuery->getQuery()->getResult();

        foreach ($blocks as $b) {
            $events[] = [
                'id' => 'block_' . $b->getId(),
                'resourceId' => $b->getBarber()->getId(),
                'title' => 'BLOQUEADO: ' . ($b->getReason() ?: 'Mantenimiento/Descanso'),
                'start' => $b->getStartAtLocal()->format('Y-m-d\TH:i:s'),
                'end' => $b->getEndAtLocal()->format('Y-m-d\TH:i:s'),
                'backgroundColor' => '#94a3b8',
                'display' => 'background', // or use a special color
                'extendedProps' => [
                    'type' => 'block',
                    'reason' => $b->getReason()
                ]
            ];
        }

        return $this->json($events, Response::HTTP_OK);
    }

    #[Route('/move/{serviceId}', name: 'api_reservation_move', methods: ['PATCH'])]
    public function moveReservation(Request $request, int $serviceId): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $service = $this->appointmentServiceRepository->find($serviceId);

        if (!$service) {
            return $this->json(['message' => 'Servicio no encontrado'], Response::HTTP_NOT_FOUND);
        }

        if (isset($data['start'])) {
            $service->setScheduledDateTime(new \DateTime($data['start']));
        }

        if (isset($data['barberId'])) {
            $barber = $this->entityManager->getRepository(BarberProfile::class)->findOneBy(['user' => $data['barberId']]);
            if ($barber) {
                $service->setBarber($barber);
            }
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Reserva movida con éxito'], Response::HTTP_OK);
    }

    #[Route('/status/{id}', name: 'api_reservation_status', methods: ['PATCH'])]
    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['status'])) {
            $appointment->setStatus(AppointmentStatus::from($data['status']));
            $this->entityManager->flush();
        }

        return $this->json(['message' => 'Estado actualizado'], Response::HTTP_OK);
    }

    #[Route('/block', name: 'api_reservation_block', methods: ['POST'])]
    public function blockTime(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $barberUser = $this->entityManager->getRepository(User::class)->find($data['barberId']);
        if (!$barberUser) {
            return $this->json(['message' => 'Barbero no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $block = new BarberTimeOff();
        $block->setBarber($barberUser);
        $block->setStartAtLocal(new \DateTime($data['start']));
        $block->setEndAtLocal(new \DateTime($data['end']));
        $block->setReason($data['reason'] ?? 'Bloqueo rápido');
        
        if (isset($data['branchId'])) {
            $branch = $this->entityManager->getRepository(Branch::class)->find($data['branchId']);
            if ($branch) $block->setBranch($branch);
        }

        $this->entityManager->persist($block);
        $this->entityManager->flush();

        return $this->json(['message' => 'Horario bloqueado'], Response::HTTP_CREATED);
    }

    private function getStatusConfigs(): array
    {
        $configs = $this->statusConfigRepository->findAll();
        $results = [];
        foreach ($configs as $config) {
            $results[$config->getStatus()] = [
                'label' => $config->getLabel(),
                'color' => $config->getColor()
            ];
        }
        return $results;
    }

    private function getDefaultColor(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::PENDING => '#fbbf24',    // Amber
            AppointmentStatus::CONFIRMED => '#3b82f6',  // Blue
            AppointmentStatus::CANCELLED => '#ef4444',  // Red
            AppointmentStatus::COMPLETED => '#10b981',  // Emerald
            AppointmentStatus::NO_SHOW => '#6b7280',    // Gray
            AppointmentStatus::IN_PROCESS => '#8b5cf6', // Violet
        };
    }
}
