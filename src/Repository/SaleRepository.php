<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\MasterProduct;
use App\Entity\Sale;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Sale>
 */
class SaleRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

// src/Repository/SaleRepository.php

    public function getReportQuery(array $filters): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('s')
            ->select(
                's.id as saleId',
                's.folio',
                's.status',
                's.saleDate',
                's.subtotal', // Agregado
                's.totalTax', // Agregado
                's.total',
                's.change',
                'u.name as cashier',
                'cb.name as cashbox'
            )
            ->leftJoin('s.user', 'u')
            ->leftJoin('s.cashBox', 'cb');

        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $qb->andWhere('s.saleDate BETWEEN :start AND :end')
                ->setParameter('start', $filters['startDate'] . ' 00:00:00')
                ->setParameter('end', $filters['endDate'] . ' 23:59:59');
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('s.folio LIKE :search OR u.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $qb->orderBy('s.id', 'DESC');

        return $qb->getQuery();
    }

    public function getTotalAccumulated(array $filters): array
    {
        $qb = $this->createQueryBuilder('s')
            // Sumamos el total y el cambio de forma independiente
            ->select('SUM(s.total) as totalSales, SUM(s.change) as totalChange')
            ->andWhere('s.status = :paidStatus')
            ->setParameter('paidStatus', \App\Enum\SaleStatus::PAID->value);

        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $qb->andWhere('s.saleDate BETWEEN :start AND :end')
                ->setParameter('start', $filters['startDate'] . ' 00:00:00')
                ->setParameter('end', $filters['endDate'] . ' 23:59:59');
        }

        if (!empty($filters['search'])) {
            $qb->leftJoin('s.user', 'u')
                ->andWhere('s.folio LIKE :search OR u.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        return [
            'totalSales' => (float)($result['totalSales'] ?? 0),
            'totalChange' => (float)($result['totalChange'] ?? 0),
            'netCash' => (float)(($result['totalSales'] ?? 0) - ($result['totalChange'] ?? 0))
        ];
    }
}
