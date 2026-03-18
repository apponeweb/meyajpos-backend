<?php

namespace App\Repository;

use App\Entity\AppointmentStatusConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<AppointmentStatusConfig>
 */
class AppointmentStatusConfigRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppointmentStatusConfig::class);
    }
}
