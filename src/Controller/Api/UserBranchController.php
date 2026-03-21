<?php

namespace App\Controller\Api;

use App\Entity\Branch;
use App\Entity\User;
use App\Entity\UserBranch;
use App\Repository\UserBranchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;

class UserBranchController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserBranchRepository $userBranchRepository
    ) {
    }

    #[Rest\Get('/user-branch', name: 'api_user_branch_list')]
    public function list(Request $request): JsonResponse
    {
        $userId = $request->query->get('userId');
        $branchId = $request->query->get('branchId');

        $qb = $this->userBranchRepository->createQueryBuilder('ub')
            ->select('ub', 'u', 'b', 'c')
            ->join('ub.user', 'u')
            ->join('ub.branch', 'b')
            ->leftJoin('b.company', 'c')
            ->where('ub.isActive = true');

        if ($userId) {
            $qb->andWhere('u.id = :userId')
               ->setParameter('userId', $userId);
        }

        if ($branchId) {
            $qb->andWhere('b.id = :branchId')
               ->setParameter('branchId', $branchId);
        }

        $qb->orderBy('u.name', 'ASC')
           ->addOrderBy('b.name', 'ASC');

        $results = $qb->getQuery()->getResult();

        $data = [];
        foreach ($results as $ub) {
            $data[] = $this->formatUserBranch($ub);
        }

        return $this->json([
            'total' => count($data),
            'results' => $data
        ], Response::HTTP_OK);
    }

    #[Rest\Get('/user-branch/{id}', name: 'api_user_branch_show')]
    public function show(int $id): JsonResponse
    {
        $userBranch = $this->userBranchRepository->find($id);

        if (!$userBranch || !$userBranch->isActive()) {
            return $this->json([
                'message' => 'Asignación no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->formatUserBranch($userBranch)
        ], Response::HTTP_OK);
    }

    #[Rest\Post('/user-branch', name: 'api_user_branch_create')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $userId = $data['userId'] ?? null;
        $branchId = $data['branchId'] ?? null;
        $isDefault = $data['isDefault'] ?? false;

        if (!$userId || !$branchId) {
            return $this->json([
                'message' => 'Validación fallida',
                'errors' => [
                    'userId' => !$userId ? 'El usuario es requerido' : null,
                    'branchId' => !$branchId ? 'La sucursal es requerida' : null
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);

        if (!$user) {
            return $this->json([
                'message' => 'Usuario no encontrado'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$branch) {
            return $this->json([
                'message' => 'Sucursal no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }

        // Verificar si ya existe la asignación
        $existing = $this->userBranchRepository->findOneBy([
            'user' => $user,
            'branch' => $branch,
            'isActive' => true
        ]);

        if ($existing) {
            return $this->json([
                'message' => 'El usuario ya tiene asignada esta sucursal'
            ], Response::HTTP_CONFLICT);
        }

        // Si es default, quitar default de las demás
        if ($isDefault) {
            $this->removeDefaultFromUser($user);
        }

        $userBranch = new UserBranch();
        $userBranch->setUser($user);
        $userBranch->setBranch($branch);
        $userBranch->setIsDefault($isDefault);

        $this->entityManager->persist($userBranch);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Sucursal asignada correctamente',
            'data' => $this->formatUserBranch($userBranch)
        ], Response::HTTP_CREATED);
    }

    #[Rest\Put('/user-branch/{id}', name: 'api_user_branch_update')]
    public function update(int $id, Request $request): JsonResponse
    {
        $userBranch = $this->userBranchRepository->find($id);

        if (!$userBranch || !$userBranch->isActive()) {
            return $this->json([
                'message' => 'Asignación no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $isDefault = $data['isDefault'] ?? $userBranch->isDefault();

        // Si se marca como default, quitar default de las demás
        if ($isDefault && !$userBranch->isDefault()) {
            $this->removeDefaultFromUser($userBranch->getUser());
        }

        $userBranch->setIsDefault($isDefault);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Asignación actualizada correctamente',
            'data' => $this->formatUserBranch($userBranch)
        ], Response::HTTP_OK);
    }

    #[Rest\Put('/user-branch/{id}/default', name: 'api_user_branch_set_default')]
    public function setDefault(int $id): JsonResponse
    {
        $userBranch = $this->userBranchRepository->find($id);

        if (!$userBranch || !$userBranch->isActive()) {
            return $this->json([
                'message' => 'Asignación no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }

        // Quitar default de las demás asignaciones del usuario
        $this->removeDefaultFromUser($userBranch->getUser());

        // Marcar esta como default
        $userBranch->setIsDefault(true);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Sucursal marcada como predeterminada',
            'data' => $this->formatUserBranch($userBranch)
        ], Response::HTTP_OK);
    }

    #[Rest\Delete('/user-branch/{id}', name: 'api_user_branch_delete')]
    public function delete(int $id): JsonResponse
    {
        $userBranch = $this->userBranchRepository->find($id);

        if (!$userBranch || !$userBranch->isActive()) {
            return $this->json([
                'message' => 'Asignación no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }

        // Soft delete
        $userBranch->setIsActive(false);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Asignación eliminada correctamente'
        ], Response::HTTP_OK);
    }

    #[Rest\Post('/user-branch/bulk', name: 'api_user_branch_bulk_assign')]
    public function bulkAssign(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $userId = $data['userId'] ?? null;
        $branchIds = $data['branchIds'] ?? [];
        $defaultBranchId = $data['defaultBranchId'] ?? null;

        if (!$userId) {
            return $this->json([
                'message' => 'El usuario es requerido'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($branchIds)) {
            return $this->json([
                'message' => 'Debe seleccionar al menos una sucursal'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            return $this->json([
                'message' => 'Usuario no encontrado'
            ], Response::HTTP_NOT_FOUND);
        }

        // Desactivar asignaciones actuales
        $currentAssignments = $this->userBranchRepository->findBy([
            'user' => $user,
            'isActive' => true
        ]);

        foreach ($currentAssignments as $assignment) {
            $assignment->setIsActive(false);
        }

        // Crear nuevas asignaciones
        $created = [];
        foreach ($branchIds as $branchId) {
            $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
            
            if (!$branch) {
                continue;
            }

            $userBranch = new UserBranch();
            $userBranch->setUser($user);
            $userBranch->setBranch($branch);
            $userBranch->setIsDefault($branchId == $defaultBranchId);

            $this->entityManager->persist($userBranch);
            $created[] = $userBranch;
        }

        $this->entityManager->flush();

        $resultData = [];
        foreach ($created as $ub) {
            $resultData[] = $this->formatUserBranch($ub);
        }

        return $this->json([
            'message' => 'Sucursales asignadas correctamente',
            'data' => $resultData
        ], Response::HTTP_CREATED);
    }

    #[Rest\Get('/user/{userId}/branches', name: 'api_user_branches')]
    public function getUserBranches(int $userId): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            return $this->json([
                'message' => 'Usuario no encontrado'
            ], Response::HTTP_NOT_FOUND);
        }

        $assignments = $this->userBranchRepository->findBy([
            'user' => $user,
            'isActive' => true
        ]);

        $data = [];
        foreach ($assignments as $ub) {
            $branch = $ub->getBranch();
            $company = $branch->getCompany();
            
            $data[] = [
                'assignment_id' => $ub->getId(),
                'branch_id' => $branch->getId(),
                'branch_name' => $branch->getName(),
                'company_id' => $company?->getId(),
                'company_name' => $company?->getName(),
                'is_default' => $ub->isDefault()
            ];
        }

        return $this->json([
            'user_id' => $userId,
            'user_name' => $user->getName() . ' ' . $user->getLastName(),
            'branches' => $data
        ], Response::HTTP_OK);
    }

    private function formatUserBranch(UserBranch $ub): array
    {
        $user = $ub->getUser();
        $branch = $ub->getBranch();
        $company = $branch->getCompany();

        return [
            'id' => $ub->getId(),
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail()
            ],
            'branch' => [
                'id' => $branch->getId(),
                'name' => $branch->getName()
            ],
            'company' => $company ? [
                'id' => $company->getId(),
                'name' => $company->getName()
            ] : null,
            'isDefault' => $ub->isDefault(),
            'createdAt' => $ub->getCreatedAt()?->format('Y-m-d H:i:s')
        ];
    }

    private function removeDefaultFromUser(User $user): void
    {
        $assignments = $this->userBranchRepository->findBy([
            'user' => $user,
            'isDefault' => true,
            'isActive' => true
        ]);

        foreach ($assignments as $assignment) {
            $assignment->setIsDefault(false);
        }
    }
}
