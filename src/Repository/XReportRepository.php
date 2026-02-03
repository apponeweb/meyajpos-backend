<?php

namespace App\Repository;

use App\Entity\CashBoxSession;
use App\Entity\XReport;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<XReport>
 */
class XReportRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, XReport::class);
    }

    public function getLastXReport(CashBoxSession $session): ?XReport
    {
        return $this->createQueryBuilder('x')
            ->where('x.cashSession = :session')
            ->andWhere('x.isActive = :active')
            ->setParameter('session', $session)
            ->setParameter('active', true)
            ->orderBy('x.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
