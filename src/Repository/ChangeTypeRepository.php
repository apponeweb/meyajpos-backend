<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\ChangeType;
use App\Entity\Currency;
use App\Entity\ServiceType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<ChangeType>
 */
class ChangeTypeRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChangeType::class);
    }


}
