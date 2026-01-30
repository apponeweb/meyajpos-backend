<?php

namespace App\Repository;

use App\Entity\CommissionGenerated;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<CommissionGenerated>
 */
class CommissionGeneratedRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommissionGenerated::class);
    }


}
