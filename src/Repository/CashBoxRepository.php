<?php

namespace App\Repository;

use App\Entity\CashBox;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<CashBox>
 */
class CashBoxRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashBox::class);
    }


}
