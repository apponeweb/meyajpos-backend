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

    public function getReportQuery(array $filters): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('x')
            ->select(
                'x.id',
                'x.reportNumber',
                'x.xReportDate',
                'x.systemTotal',
                'x.declaredTotal',
                'x.difference',
                'x.observations',
                'u.name as userName',
                'cs.id as sessionId',
                'cb.name as cashBoxName'
            )
            ->join('x.user', 'u')
            ->join('x.cashSession', 'cs')
            ->join('cs.cashBox', 'cb');

        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $qb->andWhere('x.xReportDate BETWEEN :start AND :end')
                ->setParameter('start', $filters['startDate'] . ' 00:00:00')
                ->setParameter('end', $filters['endDate'] . ' 23:59:59');
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('u.name LIKE :search OR x.reportNumber LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $qb->orderBy('x.reportNumber', 'DESC');

        return $qb->getQuery();
    }
}
