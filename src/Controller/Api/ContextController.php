<?php

namespace App\Controller\Api;

use App\Entity\Branch;
use App\Entity\User;
use App\Repository\UserBranchRepository;
use App\Service\ContextService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;

class ContextController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserBranchRepository $userBranchRepository,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ContextService $contextService
    ) {
    }

    #[Rest\Post('/select-context', name: 'api_select_context')]
    public function selectContext(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->json([
                'message' => 'Usuario no autenticado'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        
        $branchId = $data['branchId'] ?? null;
        
        if (!$branchId) {
            return $this->json([
                'message' => 'Debe seleccionar una sucursal',
                'errors' => ['branchId' => 'Este campo es requerido']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verificar que el usuario tiene acceso a esta sucursal
        if (!$this->userBranchRepository->userHasAccessToBranch($user, $branchId)) {
            return $this->json([
                'message' => 'No tiene acceso a esta sucursal'
            ], Response::HTTP_FORBIDDEN);
        }

        // Obtener la sucursal y empresa
        $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
        
        if (!$branch) {
            return $this->json([
                'message' => 'Sucursal no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }

        $company = $branch->getCompany();

        // Generar nuevo token con el contexto
        $token = $this->jwtManager->createFromPayload($user, [
            'branch_id' => $branch->getId(),
            'company_id' => $company?->getId()
        ]);

        return $this->json([
            'message' => 'Contexto seleccionado correctamente',
            'token' => $token,
            'context' => [
                'branch_id' => $branch->getId(),
                'branch_name' => $branch->getName(),
                'company_id' => $company?->getId(),
                'company_name' => $company?->getName()
            ]
        ], Response::HTTP_OK);
    }

    #[Rest\Get('/current-context', name: 'api_current_context')]
    public function getCurrentContext(): JsonResponse
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->json([
                'message' => 'Usuario no autenticado'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $branch = $this->contextService->getCurrentBranch();
        $company = $this->contextService->getCurrentCompany();

        return $this->json([
            'has_context' => $this->contextService->hasContext(),
            'context' => $branch ? [
                'branch_id' => $branch->getId(),
                'branch_name' => $branch->getName(),
                'company_id' => $company?->getId(),
                'company_name' => $company?->getName()
            ] : null,
            'available_branches' => $this->contextService->getAvailableBranches()
        ], Response::HTTP_OK);
    }
}
