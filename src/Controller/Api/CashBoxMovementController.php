<?php

namespace App\Controller\Api;

use App\Entity\CashBoxMovement;
use App\Entity\CashBoxSession;
use App\Enum\CashBoxSessionStatus;
use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use App\Form\Type\CashBoxMovementType;
use App\Repository\CashBoxMovementRepository;
use App\Repository\CashBoxSessionRepository;
use App\Repository\SalePaymentRepository;
use App\Repository\SaleRepository;
use App\Repository\UserRepository;
use App\Service\CashBoxMovementService;
use App\Service\XReportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cash-movement')]
class CashBoxMovementController extends AbstractController
{
    public function __construct(
        private Security $security
    )
    {
    }

    #[Route('', name: 'app_cash_movement_index', methods: ['GET'])]
    public function index(
        Request                   $request,
        CashBoxMovementRepository $movementRepository,
        CashBoxSessionRepository  $sessionRepo,
        UserRepository            $userRepository
    ): JsonResponse
    {
        $user = $this->getUser();
        $current = $request->query->getInt('current', 1);
        $pageSize = $request->query->getInt('pageSize', 10);


        // 2. Empaquetar filtros incluyendo la SESIÓN
        $filters = [
            'date' => $request->query->get('date'),
            'type' => $request->query->get('type'),
            'concept' => $request->query->get('concept')
        ];

        $currentUser = $userRepository->find($this->getUser());
        $isAdmin = in_array('ROLE_ADMIN', $currentUser->getRoles());

        if (!$isAdmin) {
            $activeSession = $sessionRepo->findOneBy([
                'user' => $user,
                'status' => CashBoxSessionStatus::OPEN
            ]);

            if (!$activeSession) {
                return $this->json([
                    'total' => 0,
                    'results' => [],
                    'message' => 'No hay una sesión de caja activa para este usuario.'
                ]);
            }

            $activeSession = $sessionRepo->findOneBy([
                'user' => $user,
                'status' => CashBoxSessionStatus::OPEN
            ]);
            $filters['session'] = $activeSession;
        }


        $query = $movementRepository->getWithPagination($filters);

        // 3. Paginación estándar
        $query->setFirstResult(($current - 1) * $pageSize)
            ->setMaxResults($pageSize);

        $paginator = new Paginator($query, true);

        // 4. Transformar resultados
        $results = [];
        foreach ($paginator as $movement) {
            $results[] = [
                'id' => $movement->getId(),
                'type' => $movement->getType()->value,
                'concept' => $movement->getConcept()->value,
                'amount' => number_format($movement->getAmount(), 2, '.', ','),
                'date' => $movement->getMovementDate()->format('d/m/Y H:i:s'),
                'description' => $movement->getDescription(),
                'cashBoxName' => $movement->getCashBoxSession()->getCashBox()->getName()
            ];
        }

        return $this->json([
            'total' => count($paginator),
            'results' => $results,
            'current' => $current,
            'pageSize' => $pageSize
        ]);
    }

    #[Route('/create', name: 'api_cash_movement_create', methods: ['POST'])]
    public function create(
        Request                  $request,
        CashBoxSessionRepository $sessionRepo,
        CashBoxMovementService   $movementService,
        XReportService           $reportService
    ): JsonResponse
    {
        $user = $this->security->getUser();

        $activeSession = $sessionRepo->findOneBy([
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ]);

        if (!$activeSession) {
            return $this->json([
                'message' => 'Validación fallida',
                'errors' => ['No tienes una sesión de caja abierta.']
            ], Response::HTTP_NOT_FOUND);
        }

        $movement = new CashBoxMovement();
        $form = $this->createForm(CashBoxMovementType::class, $movement);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isSubmitted() && $form->isValid()) {

            $previewData = $reportService->getPreviewData()['details'];
            $efective = 0;
            foreach ($previewData as $value) {
                if ($value['is_cash']) {
                    $efective = $value['system_amount'];
                    break;
                }
            }

            if ($movement->getType() == CashMovementType::EXTRACTION) {
                if ($movement->getAmount() > $efective) {
                    return $this->json([
                        'message' => 'Validación fallida',
                        'errors' => [
                            'children' => [
                                'amount' => [
                                    'errors' => [
                                        "El monto a retirar debe ser menor que el efectivo en caja ($efective)"
                                    ]
                                ]
                            ]
                        ]
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // LLAMADA AL SERVICIO
            $result = $movementService->createMovement($movement);

            if (!$result['success']) {
                $errorKey = (str_contains($result['error'], 'Saldo')) ? 'amount' : 'session';

                return $this->json([
                    'message' => 'Validación fallida',
                    'errors' => [
                        'children' => [
                            $errorKey => ['errors' => [$result['error']]]
                        ]
                    ],
                    'details' => $result['details'] ?? []
                ], $result['code']);
            }

            return $this->json([
                'message' => 'Movimiento registrado correctamente',
                'data' => ['id' => $result['movement']->getId()]
            ], Response::HTTP_OK);
        }

        return $this->json([
            'message' => 'Validación fallida',
            'errors' => $this->formatFormErrors($form)
        ], Response::HTTP_BAD_REQUEST);
    }


    private function formatFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = ['children' => []];
        foreach ($form->all() as $child) {
            $childErrors = [];
            foreach ($child->getErrors() as $error) {
                $childErrors[] = $error->getMessage();
            }
            if (!empty($childErrors)) {
                $errors['children'][$child->getName()] = ['errors' => $childErrors];
            }
        }
        foreach ($form->getErrors() as $error) {
            $errors['errors'][] = $error->getMessage();
        }
        return $errors;
    }
}
