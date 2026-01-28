<?php

namespace App\Controller\Api;

use App\Entity\CashBoxMovement;
use App\Entity\CashBoxSession;
use App\Enum\CashBoxSessionStatus;
use App\Enum\CashMovementType;
use App\Form\Type\CashBoxMovementType;
use App\Repository\CashBoxMovementRepository;
use App\Repository\CashBoxSessionRepository;
use App\Repository\SalePaymentRepository;
use App\Repository\SaleRepository;
use App\Service\CashBoxMovementService;
use Doctrine\ORM\EntityManagerInterface;
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
        private Security               $security
    )
    {
    }

    #[Route('/create', name: 'api_cash_movement_create', methods: ['POST'])]
    public function create(
        Request                  $request,
        CashBoxSessionRepository $sessionRepo,
        CashBoxMovementService   $movementService
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
                'errors' => ['session' => 'No tienes una sesión de caja abierta.']
            ], Response::HTTP_BAD_REQUEST);
        }

        $movement = new CashBoxMovement();
        $form = $this->createForm(CashBoxMovementType::class, $movement);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isSubmitted() && $form->isValid()) {
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
                    ]
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


    #[Route('/list-current', name: 'api_cash_movement_list', methods: ['GET'])]
//    #[IsGranted('ROLE_CASH_MOVEMENTS')]
    public function listCurrent(
        CashBoxSessionRepository  $sessionRepo,
        CashBoxMovementRepository $movementRepo,
        SalePaymentRepository     $paymentRepo // Cambiado de SaleRepository a SalePaymentRepository
    ): JsonResponse
    {
        $user = $this->security->getUser();

        // 1. Obtener la sesión activa del usuario logueado
        $activeSession = $sessionRepo->findOneBy([
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ]);

        if (!$activeSession) {
            return $this->json([
                'message' => 'Validación fallida',
                'errors' => ['session' => 'No hay una sesión activa para este usuario.']
            ], Response::HTTP_BAD_REQUEST);
        }

        // 2. Obtener historial de movimientos manuales de la sesión
        $movements = $movementRepo->findBy(
            ['cashBoxSession' => $activeSession],
            ['movementDate' => 'DESC']
        );

        // 3. Cálculos de Saldo
        $initial = (float)$activeSession->getInitialAmount();

        // Suma neta de ingresos y egresos manuales (Ingresos - Egresos)
        $movementsDiff = $movementRepo->getTotalOffsetBySession($activeSession);

        // Suma de pagos realizados en EFECTIVO en las ventas de esta sesión
        $salesCash = $paymentRepo->getTotalCashBySession($activeSession);

        // Balance final esperado en la gaveta
        $currentBalance = $initial + $movementsDiff + $salesCash;

        // 4. Transformar datos para el Frontend
        $results = array_map(function (CashBoxMovement $m) {
            return [
                'id' => $m->getId(),
                'type' => $m->getType()->value,
                'typeLabel' => $m->getType()->label(),
                'concept' => $m->getConcept()->value,
                'conceptLabel' => $m->getConcept()->label(),
                'amount' => $m->getAmount(),
                'description' => $m->getDescription(),
                'date' => $m->getMovementDate()->format('Y-m-d H:i:s')
            ];
        }, $movements);

        return $this->json([
            'message' => 'Movimientos recuperados con éxito',
            'data' => [
                'summary' => [
                    'initialAmount' => $initial,
                    'totalSalesCash' => $salesCash,
                    'manualBalance' => $movementsDiff,
                    'currentBalance' => $currentBalance
                ],
                'results' => $results
            ]
        ], Response::HTTP_OK);
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
