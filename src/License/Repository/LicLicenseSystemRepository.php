<?php

namespace App\License\Repository;

use App\License\Entity\LicLicenseSystem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LicLicenseSystem>
 */
class LicLicenseSystemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LicLicenseSystem::class);
    }
}
