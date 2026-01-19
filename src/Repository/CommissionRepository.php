<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Currency;
use App\Entity\Commission;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Commission>
 */
class CommissionRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commission::class);
    }


}
