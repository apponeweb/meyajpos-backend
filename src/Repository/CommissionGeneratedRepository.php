<?php

namespace App\Repository;

use App\Entity\CommissionGenerated;
use Doctrine\ORM\Query;
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

    public function getReportQuery(array $filters): Query
    {
        $qb = $this->createQueryBuilder('cg')
            ->select(
                'mp.name as service',
                'u.name as barber',
                'COUNT(cg.id) as quantity',
                'SUM(cg.commissionAmount) as totalCommission',
                // Usamos MAX para que sea compatible con only_full_group_by
                'MAX(cg.createdAt) as date'
            )
            ->join('cg.saleDetail', 'sd')
            ->join('sd.product', 'mp')
            ->join('cg.user', 'u')
            /* Agrupamos por el nombre del servicio, el barbero
               y la FECHA (sin hora) para que coincida con tu reporte visual
            */
            ->groupBy('service', 'barber')
            ->addGroupBy('serviceDate')
            ->addSelect("SUBSTRING(cg.createdAt, 1, 10) as HIDDEN serviceDate")
            ->orderBy('date', 'DESC');

        if (!empty($filters['startDate'])) {
            $qb->andWhere('cg.createdAt >= :startDate')
                ->setParameter('startDate', $filters['startDate'] . ' 00:00:00');
        }

        if (!empty($filters['endDate'])) {
            $qb->andWhere('cg.createdAt <= :endDate')
                ->setParameter('endDate', $filters['endDate'] . ' 23:59:59');
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('mp.name LIKE :search OR u.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->getQuery();
    }

    public function getTotalSummary(array $filters): array
    {
        $qb = $this->createQueryBuilder('cg')
            ->select(
                'SUM(cg.commissionAmount) as totalAmount',
                'COUNT(cg.id) as totalCount'
            )
            ->join('cg.saleDetail', 'sd')
            ->join('sd.product', 'mp')
            ->join('cg.user', 'u');

        // Reutilizar lógica de filtros aquí o mediante un Helper
        if (!empty($filters['startDate'])) {
            $qb->andWhere('cg.createdAt >= :startDate')
                ->setParameter('startDate', new \DateTime($filters['startDate'] . ' 00:00:00'));
        }

        if (!empty($filters['endDate'])) {
            $qb->andWhere('cg.createdAt <= :endDate')
                ->setParameter('endDate', new \DateTime($filters['endDate'] . ' 23:59:59'));
        }

        return $qb->getQuery()->getSingleResult();
    }

    public function getExportData(array $filters): array
    {
        $qb = $this->createQueryBuilder('cg')
            ->select(
                'mp.name as service',
                'u.name as barber',
                'COUNT(cg.id) as quantity',
                'SUM(cg.commissionAmount) as totalCommission',
                'MAX(cg.createdAt) as date'
            )
            ->join('cg.saleDetail', 'sd')
            ->join('sd.product', 'mp')
            ->join('cg.user', 'u')
            ->groupBy('service', 'barber', 'serviceDate')
            ->addSelect("SUBSTRING(cg.createdAt, 1, 10) as HIDDEN serviceDate")
            ->orderBy('date', 'DESC');

        // Aplicar los mismos filtros que el reporte normal
        if (!empty($filters['startDate'])) {
            $qb->andWhere('cg.createdAt >= :startDate')
                ->setParameter('startDate', $filters['startDate'] . ' 00:00:00');
        }
        if (!empty($filters['endDate'])) {
            $qb->andWhere('cg.createdAt <= :endDate')
                ->setParameter('endDate', $filters['endDate'] . ' 23:59:59');
        }
        if (!empty($filters['search'])) {
            $qb->andWhere('mp.name LIKE :search OR u.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->getQuery()->getScalarResult();
    }
}
