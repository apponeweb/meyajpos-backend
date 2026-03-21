<?php

namespace App\Controller\Api;

use App\Entity\CashBoxMovement;
use App\Entity\CashBoxSession;
use App\Enum\CashBoxSessionStatus;
use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use App\Form\Type\CashBoxOpeningType;
use App\Form\Type\CashBoxClosingType;
use App\Repository\CashBoxSessionRepository;
use App\Service\CashBoxMovementService;
use App\Service\ContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use FOS\RestBundle\Controller\Annotations as Rest;

class CashBoxSessionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security               $security,
        private ContextService         $contextService
    )
    {
    }

    #[Rest\Post('/cash-session/open', name: 'api_cash_open', methods: ['POST'])]
    public function open(Request $request, CashBoxSessionRepository $repo, CashBoxMovementService $cashBoxMovementService): JsonResponse
    {
        $user = $this->security->getUser();

        // 0. Validar que el usuario tiene contexto de sucursal seleccionado
        if (!$this->contextService->hasContext()) {
            return $this->json([
                'message' => 'Debe seleccionar una sucursal antes de abrir caja',
                'errors' => ['context' => 'No hay sucursal seleccionada']
            ], Response::HTTP_BAD_REQUEST);
        }

        $currentBranchId = $this->contextService->getCurrentBranchId();

        // 1. Validar si el usuario YA tiene alguna sesión abierta en esta sucursal
        $activeSessionForUser = $repo->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.status = :status')
            ->andWhere('s.branch = :branch')
            ->setParameter('user', $user)
            ->setParameter('status', CashBoxSessionStatus::OPEN)
            ->setParameter('branch', $currentBranchId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($activeSessionForUser) {
            return $this->json([
                'message' => 'Validación fallida',
                'errors' => [
                    'children' => [
                        'cashBox' => [
                            'errors' => ['Ya tienes una sesión abierta en la caja: ' . $activeSessionForUser->getCashBox()->getName()]
                        ]
                    ]
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        $session = new CashBoxSession();
        $form = $this->createForm(CashBoxOpeningType::class, $session);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isSubmitted() && $form->isValid()) {
            $cashBox = $session->getCashBox();

            // Validar que la caja pertenece a la sucursal seleccionada
            if ($cashBox->getBranch()->getId() !== $currentBranchId) {
                return $this->json([
                    'message' => 'Validación fallida',
                    'errors' => [
                        'children' => [
                            'cashBox' => [
                                'errors' => ['Esta caja no pertenece a la sucursal seleccionada.']
                            ]
                        ]
                    ]
                ], Response::HTTP_BAD_REQUEST);
            }

            // 2. Validar si la CAJA ya está siendo usada por otro usuario
            $activeSessionForBox = $repo->findOneBy([
                'cashBox' => $cashBox,
                'status' => CashBoxSessionStatus::OPEN
            ]);

            if ($activeSessionForBox) {
                return $this->json([
                    'message' => 'Validación fallida',
                    'errors' => [
                        'children' => [
                            'cashBox' => [
                                'errors' => ['Esta caja ya está siendo utilizada por otro cajero.']
                            ]
                        ]
                    ]
                ], Response::HTTP_BAD_REQUEST);
            }

            // Asignación automática
            $session->setBranch($cashBox->getBranch());
            $session->setOpeningDate(new \DateTime());
            $session->setUser($user);
            $session->setStatus(CashBoxSessionStatus::OPEN);
            $session->setCreatedBy($user->getId());
            $session->setUpdatedBy($user->getId());

            $this->entityManager->persist($session);
            $this->entityManager->flush();

            $movement = new CashBoxMovement();
            $movement->setCashBoxSession($session);
            $movement->setUser($user);
            $movement->setCreatedBy($user->getId());
            $movement->setUpdatedBy($user->getId());
            $movement->setType(CashMovementType::INCOME);
            $movement->setConcept(CashMovementConcept::OPEN_CASH_BOX);
            $movement->setAmount($session->getInitialAmount());
            $movement->setDescription("Apertura de caja: " . $session->getCashBox()->getName());
            $movementResult = $cashBoxMovementService->createMovement($movement);

            if (!$movementResult['success']) {
                return $this->json($movementResult);
            }

            return $this->json([
                'message' => 'Caja abierta correctamente',
                'data' => ['id' => $session->getId()]
            ], Response::HTTP_OK);
        }

        // Errores naturales del formulario (ya vienen con el formato formatFormErrors)
        return $this->json([
            'message' => 'Validación fallida',
            'errors' => $this->formatFormErrors($form)
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Rest\Post('/cash-session/close', name: 'api_cash_close', methods: ['POST'])]
    public function closeCurrent(Request $request, CashBoxSessionRepository $repo, CashBoxMovementService $cashBoxMovementService): JsonResponse
    {
        $user = $this->security->getUser();

        // 1. Buscar la sesión abierta del usuario autenticado
        $session = $repo->findOneBy([
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ]);

        if (!$session) {
            return $this->json([
                'message' => 'Validación fallida',
                'errors' => [
                    'children' => [
                        'session' => [
                            'errors' => ['No tienes ninguna sesión de caja abierta actualmente.']
                        ]
                    ]
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // 2. Procesar el formulario de cierre
        $form = $this->createForm(CashBoxClosingType::class, $session);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isSubmitted() && $form->isValid()) {
            $session->setClosingDate(new \DateTime());
            $session->setClosingUser($user);
            $session->setStatus(CashBoxSessionStatus::CLOSED);
            $session->setUpdatedBy($user->getId());

            $movement = new CashBoxMovement();
            $movement->setCashBoxSession($session);
            $movement->setUser($user);
            $movement->setCreatedBy($user->getId());
            $movement->setUpdatedBy($user->getId());
            $movement->setType(CashMovementType::INCOME);
            $movement->setConcept(CashMovementConcept::CLOSE_CASH_BOX);
            $movement->setDescription("Cierre de caja: " . $session->getCashBox()->getName());
            $movementResult = $cashBoxMovementService->createMovement($movement);
            if (!$movementResult['success']) {
                return $this->json($movementResult);
            }

            $this->entityManager->flush();

            return $this->json([
                'message' => 'Caja cerrada correctamente',
                'data' => ['id' => $session->getId()]
            ], Response::HTTP_OK);
        }

        // Errores de validación del formulario (ej: monto final incorrecto)
        return $this->json([
            'message' => 'Validación fallida',
            'errors' => $this->formatFormErrors($form)
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Rest\Get('/cash-session/status', name: 'api_cash_status', methods: ['GET'])]
    public function status(CashBoxSessionRepository $repo): JsonResponse
    {
        $user = $this->security->getUser();
        $currentBranchId = $this->contextService->getCurrentBranchId();

        // Buscar sesión abierta del usuario en la sucursal actual
        $criteria = [
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ];

        if ($currentBranchId) {
            $criteria['branch'] = $currentBranchId;
        }

        $session = $repo->findOneBy($criteria);

        if (!$session) {
            return $this->json([
                'message' => 'No hay sesión activa',
                'data' => [
                    'isOpen' => false,
                    'session' => null,
                    'hasContext' => $this->contextService->hasContext()
                ]
            ], Response::HTTP_OK);
        }

        return $this->json([
            'message' => 'Sesión activa encontrada',
            'data' => [
                'isOpen' => true,
                'hasContext' => true,
                'session' => [
                    'id' => $session->getId(),
                    'cashBoxId' => $session->getCashBox()->getId(),
                    'cashBoxName' => $session->getCashBox()->getName(),
                    'openingDate' => $session->getOpeningDate()->format('Y-m-d H:i:s'),
                    'initialAmount' => $session->getInitialAmount(),
                    'branchId' => $session->getBranch()->getId(),
                    'branchName' => $session->getBranch()->getName(),
                    'companyId' => $session->getBranch()->getCompany()?->getId(),
                    'companyName' => $session->getBranch()->getCompany()?->getName()
                ]
            ]
        ], Response::HTTP_OK);
    }


    /**
     * Replicación exacta de getFormErrors de tu BaseController
     */
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
