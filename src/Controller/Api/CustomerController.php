<?php

namespace App\Controller\Api;

use App\Entity\Customer;
use App\Entity\Appointment;
use App\Entity\AppointmentService;
use App\Repository\CustomerRepository;
use App\Repository\AppointmentRepository;
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
        private AppointmentRepository $appointmentRepository
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
        $current = $request->query->get('current', 1);
        $pageSize = $request->query->get('pageSize', 10);
        $search = $request->query->get('search');

        $queryBuilder = $this->entityManager->getRepository(Customer::class)
            ->createQueryBuilder('c')
            ->where('c.deletedAt IS NULL');

        if ($search) {
            $queryBuilder->andWhere('c.name LIKE :search OR c.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $total = count($queryBuilder->getQuery()->getResult());

        $queryBuilder->setFirstResult(($current - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->orderBy('c.createdAt', 'DESC');

        $customers = $queryBuilder->getQuery()->getResult();
        $results = [];

        foreach ($customers as $customer) {
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
                'segmentation' => $segmentation,
                'appointmentCount' => $appointmentCount,
                'isActive' => $customer->isActive()
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
            'history' => $history,
            'segmentation' => $this->calculateSegmentation($customer, count($appointments) > 0 ? $appointments[0] : null, count($appointments))
        ], Response::HTTP_OK);
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
