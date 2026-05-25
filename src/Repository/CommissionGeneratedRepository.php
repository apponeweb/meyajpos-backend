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

    public function getReportQuery(array $filters): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('cg')
            ->select(
                'mp.name as service',
                'u.name as barber',
                'u.id as barberId',
                'st.name as serviceType',
                'cg.percentage',
                'COUNT(cg.id) as quantity',
                'SUM(cg.commissionAmount) as totalCommission',
                'MAX(cg.createdAt) as date'
            )
            ->join('cg.saleDetail', 'sd')
            ->join('sd.product', 'mp')
            ->join('cg.user', 'u')
            ->join('sd.sale', 's')
            ->join('s.cashBox', 'cb')
            ->join('cb.branch', 'b')
            ->leftJoin('mp.serviceType', 'st')
            /* Agrupamos por campos lógicos.
               Nota: serviceDate se usa para separar registros por día si es necesario.
            */
            ->groupBy('mp.id')
            ->addGroupBy('mp.name')
            ->addGroupBy('u.id')
            ->addGroupBy('u.name')
            ->addGroupBy('st.name')
            ->addGroupBy('cg.percentage')
            ->orderBy('date', 'DESC');

        // --- FILTROS DE FECHA ---
        if (!empty($filters['startDate'])) {
            $qb->andWhere('cg.createdAt >= :startDate')
                ->setParameter('startDate', $filters['startDate'] . ' 00:00:00');
        }

        if (!empty($filters['endDate'])) {
            $qb->andWhere('cg.createdAt <= :endDate')
                ->setParameter('endDate', $filters['endDate'] . ' 23:59:59');
        }

        // --- FILTRO POR TIPO DE SERVICIO ---
        if (!empty($filters['serviceTypeId'])) {
            $qb->andWhere('st.id = :serviceTypeId')
                ->setParameter('serviceTypeId', $filters['serviceTypeId']);
        }

        // --- FILTRO POR BARBERO ---
        if (!empty($filters['barberId'])) {
            $qb->andWhere('u.id = :barberId')
                ->setParameter('barberId', $filters['barberId']);
        }

        // --- FILTRO POR SUCURSAL ---
        if (!empty($filters['branchId'])) {
            $qb->andWhere('b.id = :branchId')
                ->setParameter('branchId', $filters['branchId']);
        }


        // --- BÚSQUEDA GENERAL ---
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
            ->join('cg.user', 'u')
            ->join('sd.sale', 's')
            ->join('s.cashBox', 'cb')
            ->join('cb.branch', 'b')
            ->leftJoin('mp.serviceType', 'st');

        // Reutilizar lógica de filtros aquí o mediante un Helper
        if (!empty($filters['startDate'])) {
            $qb->andWhere('cg.createdAt >= :startDate')
                ->setParameter('startDate', new \DateTime($filters['startDate'] . ' 00:00:00'));
        }

        if (!empty($filters['endDate'])) {
            $qb->andWhere('cg.createdAt <= :endDate')
                ->setParameter('endDate', new \DateTime($filters['endDate'] . ' 23:59:59'));
        }
        // --- FILTRO POR TIPO DE SERVICIO ---
        if (!empty($filters['serviceTypeId'])) {
            $qb->andWhere('st.id = :serviceTypeId')
                ->setParameter('serviceTypeId', $filters['serviceTypeId']);
        }

        // --- FILTRO POR BARBERO ---
        if (!empty($filters['barberId'])) {
            $qb->andWhere('u.id = :barberId')
                ->setParameter('barberId', $filters['barberId']);
        }

        // --- FILTRO POR SUCURSAL ---
        if (!empty($filters['branchId'])) {
            $qb->andWhere('b.id = :branchId')
                ->setParameter('branchId', $filters['branchId']);
        }


        // --- BÚSQUEDA GENERAL ---
        if (!empty($filters['search'])) {
            $qb->andWhere('mp.name LIKE :search OR u.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
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
            ->join('sd.sale', 's')
            ->join('s.cashBox', 'cb')
            ->join('cb.branch', 'b')
            ->groupBy('mp.id')
            ->addGroupBy('mp.name')
            ->addGroupBy('u.id')
            ->addGroupBy('u.name')
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

        if (!empty($filters['branchId'])) {
            $qb->andWhere('b.id = :branchId')
                ->setParameter('branchId', $filters['branchId']);
        }

        return $qb->getQuery()->getScalarResult();
    }
}
