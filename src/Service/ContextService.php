<?php

namespace App\Service;

use App\Entity\Branch;
use App\Entity\Company;
use App\Entity\User;
use App\Repository\UserBranchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class ContextService
{
    public function __construct(
        private readonly Security $security,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserBranchRepository $userBranchRepository
    ) {
    }

    /**
     * Obtiene el ID de la sucursal actual desde el JWT
     */
    public function getCurrentBranchId(): ?int
    {
        $token = $this->tokenStorage->getToken();
        
        if (!$token) {
            return null;
        }

        $payload = $token->getAttributes();
        
        return $payload['branch_id'] ?? null;
    }

    /**
     * Obtiene el ID de la empresa actual desde el JWT
     */
    public function getCurrentCompanyId(): ?int
    {
        $token = $this->tokenStorage->getToken();
        
        if (!$token) {
            return null;
        }

        $payload = $token->getAttributes();
        
        return $payload['company_id'] ?? null;
    }

    /**
     * Obtiene la entidad Branch actual
     */
    public function getCurrentBranch(): ?Branch
    {
        $branchId = $this->getCurrentBranchId();
        
        if (!$branchId) {
            return null;
        }

        return $this->entityManager->getRepository(Branch::class)->find($branchId);
    }

    /**
     * Obtiene la entidad Company actual
     */
    public function getCurrentCompany(): ?Company
    {
        $companyId = $this->getCurrentCompanyId();
        
        if (!$companyId) {
            return null;
        }

        return $this->entityManager->getRepository(Company::class)->find($companyId);
    }

    /**
     * Verifica si el usuario tiene contexto seleccionado
     */
    public function hasContext(): bool
    {
        return $this->getCurrentBranchId() !== null;
    }

    /**
     * Obtiene las sucursales disponibles para el usuario actual
     */
    public function getAvailableBranches(): array
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return [];
        }

        $userBranches = $this->userBranchRepository->findBranchesWithCompanyByUser($user);
        
        $result = [];
        foreach ($userBranches as $userBranch) {
            $branch = $userBranch->getBranch();
            $company = $branch->getCompany();
            
            $result[] = [
                'branch_id' => $branch->getId(),
                'branch_name' => $branch->getName(),
                'company_id' => $company?->getId(),
                'company_name' => $company?->getName(),
                'is_default' => $userBranch->isDefault()
            ];
        }

        return $result;
    }

    /**
     * Verifica si el usuario tiene acceso a una sucursal específica
     */
    public function userHasAccessToBranch(int $branchId): bool
    {
        $user = $this->security->getUser();
        
        if (!$user instanceof User) {
            return false;
        }

        return $this->userBranchRepository->userHasAccessToBranch($user, $branchId);
    }
}
