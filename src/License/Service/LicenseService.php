<?php

namespace App\License\Service;

use App\Entity\User;
use App\License\Entity\LicLicense;
use App\License\Entity\LicLicenseSystem;
use App\License\Entity\LicSystem;
use App\License\Repository\LicLicenseRepository;
use App\License\Repository\LicSystemRepository;
use App\Repository\BarberProfileRepository;
use Doctrine\ORM\EntityManagerInterface;

class LicenseService
{
    public function __construct(
        private readonly LicLicenseRepository $licenseRepository,
        private readonly LicSystemRepository  $systemRepository,
        private readonly BarberProfileRepository $barberProfileRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    private function getUserPhoto(User $user): ?string
    {
        $profile = $this->barberProfileRepository->findOneBy(['user' => $user]);
        if ($profile && $profile->getPhotoUrl()) {
            return $profile->getPhotoUrl();
        }
        return $user->getPhotoUrl();
    }

    public function getActiveLicenseForUser(User $user): ?LicLicense
    {
        return $this->licenseRepository->findActiveByUser($user);
    }

    public function isLicenseExpired(LicLicense $license): bool
    {
        return $license->isExpired();
    }

    public function getLicenseClaims(User $user): ?array
    {
        $license = $this->getActiveLicenseForUser($user);
        if (!$license) {
            return null;
        }

        return [
            'licenseId'   => $license->getId(),
            'companyId'   => $license->getCompany()->getId(),
            'companyName' => $license->getCompany()->getName(),
            'maxBranches' => $license->getMaxBranches(),
            'maxBarbers'  => $license->getMaxBarbers(),
            'expiresAt'   => $license->getExpiresAt()?->format('Y-m-d'),
            'activatedAt' => $license->getActivatedAt()?->format('Y-m-d H:i:s'),
            'isExpired'   => $license->isExpired(),
            'systems'     => $license->getSystemCodes(),
        ];
    }

    public function syncLicenseSystems(LicLicense $license, array $systemCodes): void
    {
        $license->clearLicenseSystems();
        $this->entityManager->flush();

        foreach ($systemCodes as $code) {
            $system = $this->systemRepository->findOneBy(['code' => $code]);
            if (!$system) {
                continue;
            }
            $licenseSystem = new LicLicenseSystem();
            $licenseSystem->setLicense($license);
            $licenseSystem->setSystem($system);
            $this->entityManager->persist($licenseSystem);
        }
    }

    public function formatLicenseForResponse(LicLicense $license): array
    {
        return [
            'id'          => $license->getId(),
            'user'        => [
                'id'    => $license->getUser()->getId(),
                'name'  => $license->getUser()->getName() . ' ' . $license->getUser()->getLastName(),
                'email' => $license->getUser()->getEmail(),
                'photoUrl' => $this->getUserPhoto($license->getUser()),
            ],
            'company'     => [
                'id'   => $license->getCompany()->getId(),
                'name' => $license->getCompany()->getName(),
            ],
            'maxBranches' => $license->getMaxBranches(),
            'maxBarbers'  => $license->getMaxBarbers(),
            'startDate'   => $license->getStartDate()->format('Y-m-d'),
            'durationDays'=> $license->getDurationDays(),
            'expiresAt'   => $license->getExpiresAt()?->format('Y-m-d'),
            'activatedAt' => $license->getActivatedAt()?->format('Y-m-d H:i:s'),
            'isActive'    => $license->isActive(),
            'licenseKey'   => $license->getLicenseKey(),
            'type'         => $license->getType() ? [
                'id' => $license->getType()->getId(),
                'name' => $license->getType()->getName(),
                'code' => $license->getType()->getCode()
            ] : null,
            'maxActivations' => $license->getMaxActivations(),
            'hardwareIds'  => $license->getHardwareIds(),
            'isExpired'   => $license->isExpired(),
            'notes'       => $license->getNotes(),
            'systems'     => $license->getSystemCodes(),
            'status'      => $this->resolveStatus($license),
            'createdAt'   => $license->getCreatedAt()?->format('d/m/Y H:i:s'),
            'updatedAt'   => $license->getUpdatedAt()?->format('d/m/Y H:i:s'),
        ];
    }

    public function resolveStatus(LicLicense $license): string
    {
        if (!$license->isActive()) {
            return 'inactive';
        }
        if ($license->getActivatedAt() === null) {
            return 'pending';
        }
        if ($license->isExpired()) {
            return 'expired';
        }
        $expires = $license->getExpiresAt();
        if (!$expires) return 'active';

        $daysLeft = (new \DateTime('today'))->diff($expires)->days;
        if ($daysLeft <= 7) {
            return 'expiring';
        }
        return 'active';
    }
}
