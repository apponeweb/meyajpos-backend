<?php

namespace App\Controller\Api;

use App\Entity\CashBoxSession;
use App\Enum\CashBoxSessionStatus;
use App\Form\Type\CashBoxOpeningType;
use App\Form\Type\CashBoxClosingType;
use App\Repository\CashBoxSessionRepository;
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
        private Security               $security
    )
    {
    }

    #[Rest\Post('/cash-session/open', name: 'api_cash_open', methods: ['POST'])]
    public function open(Request $request, CashBoxSessionRepository $repo): JsonResponse
    {
        $user = $this->security->getUser();

        // 1. Validar si el usuario YA tiene alguna sesión abierta
        $activeSessionForUser = $repo->findOneBy([
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ]);

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

            $this->entityManager->persist($session);
            $this->entityManager->flush();

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
    public function closeCurrent(Request $request, CashBoxSessionRepository $repo): JsonResponse
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

        $session = $repo->findOneBy([
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ]);

        if (!$session) {
            return $this->json([
                'message' => 'No hay sesión activa',
                'data' => [
                    'isOpen' => false,
                    'session' => null
                ]
            ], Response::HTTP_OK);
        }

        return $this->json([
            'message' => 'Sesión activa encontrada',
            'data' => [
                'isOpen' => true,
                'session' => [
                    'id' => $session->getId(),
                    'cashBoxId' => $session->getCashBox()->getId(),
                    'cashBoxName' => $session->getCashBox()->getName(),
                    'openingDate' => $session->getOpeningDate()->format('Y-m-d H:i:s'),
                    'initialAmount' => $session->getInitialAmount(),
                    'branchName' => $session->getBranch()->getName()
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
