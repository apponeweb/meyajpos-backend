<?php

namespace App\Repository;

use App\Entity\BarberProfile;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<BarberProfile>
 */
class BarberProfileRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BarberProfile::class);
    }
}
