<?php

namespace App\Repository;

use App\Entity\BarberTimeOff;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<BarberTimeOff>
 */
class BarberTimeOffRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BarberTimeOff::class);
    }
}
