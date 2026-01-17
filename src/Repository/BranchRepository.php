<?php

namespace App\Repository;

use App\Entity\Branch;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Branch>
 */
class BranchRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Branch::class);
    }


}
