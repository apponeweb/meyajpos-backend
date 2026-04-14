<?php

namespace App\License\Service;

use App\Entity\User;
use App\License\Repository\LicLicenseRepository;
use App\Repository\BranchRepository;
use App\Repository\UserRepository;

/**
 * Servicio para validar límites de licencia en el POS.
 * Puede ser reutilizado en cualquier sistema que importe este módulo.
 */
class LicenseValidatorService
{
    public function __construct(
        private readonly LicenseService       $licenseService,
        private readonly BranchRepository     $branchRepository,
        private readonly UserRepository       $userRepository,
    ) {}

    /**
     * Verifica si el usuario puede crear más sucursales según su licencia.
     */
    public function canCreateBranch(User $user): array
    {
        $license = $this->licenseService->getActiveLicenseForUser($user);
        if (!$license) {
            return ['allowed' => true, 'reason' => null];
        }

        $currentCount = $this->branchRepository->countByCompany($license->getCompany()->getId());
        $allowed = $currentCount < $license->getMaxBranches();

        return [
            'allowed' => $allowed,
            'current' => $currentCount,
            'max'     => $license->getMaxBranches(),
            'reason'  => $allowed ? null : "Ha alcanzado el límite de {$license->getMaxBranches()} sucursal(es) de su licencia.",
        ];
    }

    /**
     * Verifica si el usuario puede registrar más barberos según su licencia.
     */
    public function canCreateBarber(User $user): array
    {
        $license = $this->licenseService->getActiveLicenseForUser($user);
        if (!$license) {
            return ['allowed' => true, 'reason' => null];
        }

        $currentCount = $this->userRepository->countBarbers($license->getCompany()->getId());
        $allowed = $currentCount < $license->getMaxBarbers();

        return [
            'allowed' => $allowed,
            'current' => $currentCount,
            'max'     => $license->getMaxBarbers(),
            'reason'  => $allowed ? null : "Ha alcanzado el límite de {$license->getMaxBarbers()} barbero(s) de su licencia.",
        ];
    }
}
