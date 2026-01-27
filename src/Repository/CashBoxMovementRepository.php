<?php

namespace App\Repository;

use App\Entity\CashBoxMovement;
use App\Entity\CashBoxSession;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<CashBoxMovement>
 */
class CashBoxMovementRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashBoxMovement::class);
    }

    public function getTotalOffsetBySession(CashBoxSession $session): float
    {
        // Sumamos Ingresos (1) y Egresos (2) en una sola consulta
        $qb = $this->createQueryBuilder('m')
            ->select('SUM(CASE WHEN m.type = 1 THEN m.amount ELSE 0 END) as totalIn')
            ->addSelect('SUM(CASE WHEN m.type = 2 THEN m.amount ELSE 0 END) as totalOut')
            ->where('m.cashBoxSession = :session')
            ->andWhere('m.isActive = :active')
            ->setParameter('session', $session)
            ->setParameter('active', true);

        $result = $qb->getQuery()->getOneOrNullResult();

        return (float)($result['totalIn'] ?? 0) - (float)($result['totalOut'] ?? 0);
    }
}
