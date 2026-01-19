<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Tip;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Tip>
 */
class TipRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tip::class);
    }


}
