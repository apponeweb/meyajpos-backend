<?php

namespace App\Controller\Api;

use App\Entity\Customer;
use App\Entity\Appointment;
use App\Entity\AppointmentService;
use App\Enum\AppointmentStatus;
use App\Repository\CustomerRepository;
use App\Repository\AppointmentRepository;
use App\Service\Phone\PhoneNumberService;
use App\Service\WhatsApp\WhatsAppClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/customer')]
class CustomerController extends BaseController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        private AppointmentRepository $appointmentRepository,
        private WhatsAppClient $whatsAppClient,
        private PhoneNumberService $phoneNumberService
    ) {
        parent::__construct($entityManager, $security);
    }

    protected function getEntityClass(): string
    {
        return Customer::class;
    }

    protected function getFormTypeClass(): string
    {
        // For basic CRUD operations, we can use a generic form if needed, 
        // but for now let's focus on the special logic.
        return '';
    }

    #[Route('', name: 'api_customer_list', methods: ['GET'])]
    public function listCustomers(Request $request): JsonResponse
    {
        $current = max(1, (int) $request->query->get('current', 1));
        $pageSize = max(1, (int) $request->query->get('pageSize', 10));
        $search = $request->query->get('search');
        $branchId = $request->query->get('branchId');
        $sortBy = $request->query->get('sortBy');
        $sortOrder = strtolower((string) $request->query->get('sortOrder', 'asc')) === 'desc' ? 'desc' : 'asc';

        $queryBuilder = $this->entityManager->getRepository(Customer::class)
            ->createQueryBuilder('c')
            ->where('c.deletedAt IS NULL');

        if ($search) {
            $queryBuilder->andWhere('c.name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($branchId) {
            $queryBuilder->andWhere('c.branch = :branchId')
                ->setParameter('branchId', $branchId);
        }

        $customers = $queryBuilder->getQuery()->getResult();
        $total = count($customers);

        $visitMap = $this->buildVisitMap($customers);

        if ($sortBy === 'nextVisit') {
            usort($customers, function (Customer $a, Customer $b) use ($visitMap, $sortOrder) {
                $dateA = $visitMap[$a->getId()]['nextVisitIso'] ?? null;
                $dateB = $visitMap[$b->getId()]['nextVisitIso'] ?? null;

                if ($dateA === null && $dateB === null) {
                    return 0;
                }
                if ($dateA === null) {
                    return 1;
                }
                if ($dateB === null) {
                    return -1;
                }

                $cmp = strcmp($dateA, $dateB);

                return $sortOrder === 'desc' ? -$cmp : $cmp;
            });
        }

        $pageCustomers = array_slice($customers, ($current - 1) * $pageSize, $pageSize);

        $results = [];

        foreach ($pageCustomers as $customer) {
            $visit = $visitMap[$customer->getId()] ?? ['nextVisit' => null, 'nextVisitIso' => null, 'daysUntilNextVisit' => null];

            $lastAppointment = $this->appointmentRepository->findOneBy(
                ['customer' => $customer],
                ['createdAt' => 'DESC']
            );

            $lastVisitDate = null;
            if ($lastAppointment && count($lastAppointment->getServices()) > 0) {
                $lastVisitDate = $lastAppointment->getServices()->first()->getScheduledDateTime()?->format('d/m/Y H:i:s');
            }

            $appointmentCount = $this->appointmentRepository->count(['customer' => $customer]);

            $segmentation = $this->calculateSegmentation($customer, $lastAppointment, $appointmentCount);

            $results[] = [
                'id' => $customer->getId(),
                'name' => $customer->getName(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'lastVisit' => $lastVisitDate,
                'nextVisit' => $visit['nextVisit']?->format('d/m/Y'),
                'nextVisitIso' => $visit['nextVisitIso'],
                'daysUntilNextVisit' => $visit['daysUntilNextVisit'],
                'visitFrequencyDays' => $customer->getVisitFrequencyDays(),
                'segmentation' => $segmentation,
                'appointmentCount' => $appointmentCount,
                'isActive' => $customer->isActive(),
                'branch' => $customer->getBranch() ? [
                    'id' => $customer->getBranch()->getId(),
                    'name' => $customer->getBranch()->getName()
                ] : null
            ];
        }

        return $this->json([
            'results' => $results,
            'total' => $total
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_customer_show', methods: ['GET'])]
    public function showCustomer(Customer $customer): JsonResponse
    {
        $appointments = $this->appointmentRepository->findBy(
            ['customer' => $customer],
            ['createdAt' => 'DESC']
        );

        $history = [];
        foreach ($appointments as $app) {
            foreach ($app->getServices() as $service) {
                $history[] = [
                    'date' => $service->getScheduledDateTime()->format('d/m/Y H:i:s'),
                    'service' => $service->getService()->getName(),
                    'barber' => $service->getBarber()->getUser()->getName(),
                    'branch' => $app->getBranch()?->getName(),
                    'price' => $service->getPrice(),
                    'status' => $app->getStatus()->getLabel()
                ];
            }
        }

        return $this->json([
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'countryCode' => $customer->getCountryCode(),
            'notes' => $customer->getNotes(),
            'preferences' => $customer->getPreferences(),
            'visitFrequencyDays' => $customer->getVisitFrequencyDays(),
            'history' => $history,
            'segmentation' => $this->calculateSegmentation($customer, count($appointments) > 0 ? $appointments[0] : null, count($appointments))
        ], Response::HTTP_OK);
    }

    #[Route('/send-messages', name: 'api_customer_send_messages', methods: ['POST'])]
    public function sendMessages(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $customerIds = array_values(array_unique(array_filter(array_map('intval', (array) ($data['customerIds'] ?? [])))));
        $customMessage = trim((string) ($data['message'] ?? ''));

        if ($customerIds === []) {
            return $this->json(['message' => 'No se seleccionaron clientes.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var Customer[] $customers */
        $customers = $this->entityManager->getRepository(Customer::class)->findBy(['id' => $customerIds]);

        if ($customers === []) {
            return $this->json(['message' => 'No se encontraron los clientes seleccionados.'], Response::HTTP_NOT_FOUND);
        }

        $visitMap = $this->buildVisitMap($customers);

        $summary = [
            'total' => count($customers),
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        foreach ($customers as $customer) {
            $name = (string) ($customer->getName() ?? 'Cliente');
            $recipient = $this->phoneNumberService->normalizeForWhatsApp(
                $customer->getCountryCode(),
                $customer->getPhone()
            );

            if ($recipient === null || $recipient === '') {
                $summary['skipped']++;
                $summary['details'][] = [
                    'customerId' => $customer->getId(),
                    'name' => $name,
                    'status' => 'skipped',
                    'reason' => 'Sin teléfono válido',
                ];

                continue;
            }

            $visit = $visitMap[$customer->getId()] ?? null;
            $message = $customMessage !== ''
                ? $this->renderCustomerMessage($customMessage, $customer, $visit)
                : $this->defaultReminderMessage($customer, $visit);

            try {
                $this->whatsAppClient->sendTextMessage($recipient, $message);
                $summary['sent']++;
                $summary['details'][] = [
                    'customerId' => $customer->getId(),
                    'name' => $name,
                    'status' => 'sent',
                ];
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $summary['details'][] = [
                    'customerId' => $customer->getId(),
                    'name' => $name,
                    'status' => 'failed',
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return $this->json($summary, Response::HTTP_OK);
    }

    /**
     * Calcula la próxima visita de cada cliente:
     * 1) La cita futura más próxima agendada, si existe.
     * 2) Si no hay cita futura y el cliente indicó frecuencia, última cita + frecuencia.
     *
     * @param Customer[] $customers
     * @return array<int, array{nextVisit: ?\DateTime, nextVisitIso: ?string, daysUntilNextVisit: ?int}>
     */
    private function buildVisitMap(array $customers): array
    {
        $visitMap = [];
        $customerIds = [];

        foreach ($customers as $customer) {
            $customerIds[] = $customer->getId();
            $visitMap[$customer->getId()] = [
                'nextVisit' => null,
                'nextVisitIso' => null,
                'daysUntilNextVisit' => null,
            ];
        }

        if ($customerIds === []) {
            return $visitMap;
        }

        $now = new \DateTime();

        /** @var array<int, array{cid: int, dt: \DateTime}> $rows */
        $rows = $this->entityManager->createQuery(
            'SELECT IDENTITY(a.customer) AS cid, aps.scheduledDateTime AS dt
             FROM App\Entity\AppointmentService aps
             JOIN aps.appointment a
             WHERE a.customer IN (:customers)
               AND aps.deletedAt IS NULL
               AND a.deletedAt IS NULL
               AND a.status != :cancelled'
        )
            ->setParameter('customers', $customers)
            ->setParameter('cancelled', AppointmentStatus::CANCELLED)
            ->getArrayResult();

        $futureMin = [];
        $pastMax = [];

        foreach ($rows as $row) {
            $cid = (int) $row['cid'];
            $dt = $row['dt'];

            if (!$dt instanceof \DateTimeInterface) {
                continue;
            }

            if ($dt >= $now) {
                if (!isset($futureMin[$cid]) || $dt < $futureMin[$cid]) {
                    $futureMin[$cid] = $dt;
                }
            } else {
                if (!isset($pastMax[$cid]) || $dt > $pastMax[$cid]) {
                    $pastMax[$cid] = $dt;
                }
            }
        }

        foreach ($customers as $customer) {
            $cid = $customer->getId();
            $nextVisit = $futureMin[$cid] ?? null;

            if ($nextVisit === null && isset($pastMax[$cid]) && $customer->getVisitFrequencyDays()) {
                $nextVisit = (clone $pastMax[$cid])->modify('+' . $customer->getVisitFrequencyDays() . ' days');
            }

            if ($nextVisit !== null) {
                $diffSeconds = $nextVisit->getTimestamp() - $now->getTimestamp();
                $visitMap[$cid] = [
                    'nextVisit' => $nextVisit,
                    'nextVisitIso' => $nextVisit->format('Y-m-d'),
                    'daysUntilNextVisit' => (int) floor($diffSeconds / 86400),
                ];
            }
        }

        return $visitMap;
    }

    private function renderCustomerMessage(string $template, Customer $customer, ?array $visit): string
    {
        $nextVisitLabel = '';
        if ($visit !== null && $visit['nextVisit'] !== null) {
            $nextVisitLabel = ' Tu próxima visita sugerida es el ' . $visit['nextVisit']->format('d/m/Y') . '.';
        }

        return strtr($template, [
            '{nombre}' => (string) ($customer->getName() ?? 'Cliente'),
            '{proxima_visita}' => $nextVisitLabel,
        ]);
    }

    private function defaultReminderMessage(Customer $customer, ?array $visit): string
    {
        $name = (string) ($customer->getName() ?? 'Cliente');
        $visitLine = '';

        if ($visit !== null && $visit['nextVisit'] !== null) {
            $visitLine = "\n\nSegún tu frecuencia de visitas, tu próxima cita sugerida es el *"
                . $visit['nextVisit']->format('d/m/Y') . "*.";
        }

        return "Hola *{$name}* 👋\n\nSoy el asistente de *Tio's Barber*."
            . $visitLine
            . "\n\n¿Te gustaría agendar tu próxima cita? Responde a este mensaje o escribe *agenda* para reservar.";
    }

    #[Route('/{id}/preferences', name: 'api_customer_update_preferences', methods: ['PATCH'])]
    public function updatePreferences(Request $request, Customer $customer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['preferences'])) {
            $customer->setPreferences($data['preferences']);
        }
        
        if (isset($data['notes'])) {
            $customer->setNotes($data['notes']);
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Preferencias actualizadas correctamente'], Response::HTTP_OK);
    }

    private function calculateSegmentation(Customer $customer, ?Appointment $lastAppointment, int $appointmentCount): array
    {
        $tags = [];
        $now = new \DateTime();

        // 1. Nuevo
        $diffCreated = $customer->getCreatedAt()->diff($now)->days;
        if ($diffCreated <= 30) {
            $tags[] = 'Nuevo';
        }

        // 2. VIP
        if ($appointmentCount >= 10) {
            $tags[] = 'VIP';
        }

        // 3. En Riesgo
        if ($lastAppointment) {
            $diffLast = $lastAppointment->getCreatedAt()->diff($now)->days;
            if ($diffLast > 45) {
                $tags[] = 'En Riesgo';
            }
        } elseif ($diffCreated > 45) {
             $tags[] = 'En Riesgo';
        }

        return $tags;
    }
}
