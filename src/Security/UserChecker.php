<?php

namespace App\Security;

use App\Entity\User as AppUser;
use App\License\Service\LicenseService;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly LicenseService $licenseService
    ) {}

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        if (!$user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException('account_disabled');
        }

        // Los usuarios con ROLE_LICENSE_ADMIN no necesitan licencia propia
        if (in_array('ROLE_LICENSE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        // Verificar si el usuario tiene licencia activa y no vencida
        $license = $this->licenseService->getActiveLicenseForUser($user);

        if ($license !== null && $this->licenseService->isLicenseExpired($license)) {
            throw new CustomUserMessageAccountStatusException('license_expired');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        if (!$user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException('account_disabled');
        }
    }
}
