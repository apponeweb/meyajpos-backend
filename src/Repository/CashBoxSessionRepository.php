<?php

namespace App\Repository;

use App\Entity\CashBoxSession;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<CashBoxSession>
 */
class CashBoxSessionRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashBoxSession::class);
    }


}
