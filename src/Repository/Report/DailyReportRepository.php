<?php

namespace App\Repository\Report;

use App\Entity\Report\DailyReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

class DailyReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyReport::class);
    }

    public function getReportQuery(array $filters): Query
    {
        $qb = $this->createQueryBuilder('r');

        // Aplicamos los filtros compartidos
        $this->applyReportFilters($qb, $filters);

        $qb->orderBy('r.id', 'DESC');

        return $qb->getQuery();
    }

    public function getDetailsTotalAccumulated(array $filters): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select(
                'SUM(r.quantity) as sumQuantity',
                'SUM(r.unitPrice) as sumUnitPrice',
                'SUM(r.total) as sumTotal',
                'SUM(r.tipAmount) as sumTips',
                "SUM(CASE WHEN r.paymentMethodId = 3 THEN r.paymentAmount ELSE 0 END) as totalCash",
                "SUM(CASE WHEN r.paymentMethodId = 2 THEN r.paymentAmount ELSE 0 END) as totalCard",
                "SUM(CASE WHEN r.paymentMethodId = 1 THEN r.paymentAmount ELSE 0 END) as totalTransfer"
            );

        // Aplicamos los mismos filtros compartidos
        $this->applyReportFilters($qb, $filters);

        return $qb->getQuery()->getOneOrNullResult() ?: [];
    }

    /**
     * Centraliza la lógica de filtrado para evitar duplicidad
     */
    private function applyReportFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            // Al ser un objeto DateTime o una columna DATETIME, Doctrine lo entiende nativamente
            $qb->andWhere('r.saleDate BETWEEN :start AND :end')
                ->setParameter('start', $filters['startDate'] . ' 00:00:00')
                ->setParameter('end', $filters['endDate'] . ' 23:59:59');
        }

        if (!empty($filters['barberId'])) {
            $qb->andWhere('r.barberId = :barberId')
                ->setParameter('barberId', $filters['barberId']);
        }

        if (!empty($filters['serviceTypeId'])) {
            $qb->andWhere('r.serviceTypeId = :serviceTypeId')
                ->setParameter('serviceTypeId', $filters['serviceTypeId']);
        }

        if (!empty($filters['paymentTypeId'])) {
            $qb->andWhere('r.paymentMethodId = :paymentTypeId')
                ->setParameter('paymentTypeId', $filters['paymentTypeId']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('r.ticketFolio LIKE :search OR r.productServiceName LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['branchId'])) {
            $qb->andWhere('r.branchId = :branchId')
                ->setParameter('branchId', $filters['branchId']);
        }
    }
}
