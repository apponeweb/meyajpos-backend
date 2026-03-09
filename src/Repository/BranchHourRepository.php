<?php

namespace App\Repository;

use App\Entity\BranchHour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<BranchHour>
 */
class BranchHourRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BranchHour::class);
    }
}
