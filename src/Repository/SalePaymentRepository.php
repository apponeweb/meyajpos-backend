<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\CashBoxSession;
use App\Entity\PaymentType;
use App\Entity\SalePayment;
use App\Enum\PaymentTypeEnum;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<SalePayment>
 */
class SalePaymentRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalePayment::class);
    }


    public function getTotalCashBySession(CashBoxSession $session): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.amountReceived)')
            ->innerJoin('p.sale', 's')
            ->innerJoin('p.paymentType', 'pt')
            ->where('s.cashBox = :cashBox')
            ->andWhere('s.saleDate >= :openingDate')
            ->andWhere('pt.name = :methodName')
            ->setParameter('cashBox', $session->getCashBox())
            ->setParameter('openingDate', $session->getOpeningDate())
            ->setParameter('methodName', 'Efectivo');

        if ($session->getClosingDate()) {
            $qb->andWhere('s.saleDate <= :closingDate')
                ->setParameter('closingDate', $session->getClosingDate());
        }

        return (float)$qb->getQuery()->getSingleScalarResult();
    }

    public function getSummaryBySessionAndType(CashBoxSession $session, PaymentType $paymentType): array
    {
        $result = $this->createQueryBuilder('sp')
            ->select('SUM(sp.amountReceived) as amount')
            ->addSelect('COUNT(sp.id) as count')
            ->innerJoin('sp.sale', 's')
            ->where('s.cashBox = :session')
            ->andWhere('sp.paymentType = :paymentType')
            ->andWhere('sp.isActive = :active')
            ->andWhere('s.isActive = :active')
            ->setParameter('session', $session->getCashBox())
            ->setParameter('paymentType', $paymentType)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'amount' => $result['amount'] ?? '0.00',
            'count' => (int)($result['count'] ?? 0)
        ];
    }


    public function getDetailsReportQuery(array $filters): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('sp')
            // Seleccionamos las entidades necesarias para evitar el error de ResultSetMapping
            ->select('sp', 'pt', 's', 'sd', 'p', 'st', 'u')
            ->innerJoin('sp.paymentType', 'pt')
            ->innerJoin('sp.sale', 's')
            ->innerJoin('s.details', 'sd') // Aquí se genera la fila por cada servicio del pago
            ->innerJoin('sd.product', 'p')
            ->leftJoin('p.serviceType', 'st')
            ->leftJoin('sd.serviceProvider', 'u');

        // Aplicar filtros (usando los alias definidos arriba)
        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $qb->andWhere('s.saleDate BETWEEN :start AND :end')
                ->setParameter('start', $filters['startDate'] . ' 00:00:00')
                ->setParameter('end', $filters['endDate'] . ' 23:59:59');
        }

        if (!empty($filters['barberId'])) {
            $qb->andWhere('u.id = :barberId')
                ->setParameter('barberId', $filters['barberId']);
        }

        if (!empty($filters['serviceTypeId'])) {
            $qb->andWhere('st.id = :serviceTypeId')
                ->setParameter('serviceTypeId', $filters['serviceTypeId']);
        }
        if (!empty($filters['paymentTypeId'])) {
            $qb->andWhere('pt.id = :paymentTypeId')
                ->setParameter('paymentTypeId', $filters['paymentTypeId']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('s.folio LIKE :search OR p.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $qb->orderBy('s.saleDate', 'DESC');

        return $qb->getQuery();
    }


    public function getDetailsTotalAccumulated(array $filters): array
    {
        $qb = $this->createQueryBuilder('sp')
            ->select(
                'SUM(sd.quantity) as sumQuantity',
                'SUM(sd.unitPrice) as sumUnitPrice',
                'SUM(sd.total) as sumTotal',
                // Usamos los valores del Enum directamente para las sumas condicionales
                sprintf("SUM(CASE WHEN pt.id = %d THEN sp.amountReceived ELSE 0 END) as totalTransfer", PaymentTypeEnum::TRANSFER->value),
                sprintf("SUM(CASE WHEN pt.id = %d THEN sp.amountReceived ELSE 0 END) as totalCard", PaymentTypeEnum::CARD->value),
                sprintf("SUM(CASE WHEN pt.id = %d THEN sp.amountReceived ELSE 0 END) as totalCash", PaymentTypeEnum::CASH->value)
            )
            ->innerJoin('sp.paymentType', 'pt')
            ->innerJoin('sp.sale', 's')
            ->innerJoin('s.details', 'sd')
            ->innerJoin('sd.product', 'p')
            ->leftJoin('p.serviceType', 'st')
            ->leftJoin('sd.serviceProvider', 'u');

        // --- Bloque de Filtros ---
        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $qb->andWhere('s.saleDate BETWEEN :start AND :end')
                ->setParameter('start', $filters['startDate'] . ' 00:00:00')
                ->setParameter('end', $filters['endDate'] . ' 23:59:59');
        }

        if (!empty($filters['barberId'])) {
            $qb->andWhere('u.id = :barberId')
                ->setParameter('barberId', $filters['barberId']);
        }

        if (!empty($filters['serviceTypeId'])) {
            $qb->andWhere('st.id = :serviceTypeId')
                ->setParameter('serviceTypeId', $filters['serviceTypeId']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('s.folio LIKE :search OR p.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        $sumTotal = (float)($result['sumTotal'] ?? 0);
        $sumUnitPrice = (float)($result['sumUnitPrice'] ?? 0);

        return [
            'sumQuantity' => (float)($result['sumQuantity'] ?? 0),
            'sumUnitPrice' => $sumUnitPrice,
            'sumTotal' => $sumTotal,
            'totalTransfer' => (float)($result['totalTransfer'] ?? 0),
            'totalCard' => (float)($result['totalCard'] ?? 0),
            'totalCash' => (float)($result['totalCash'] ?? 0),
            'sumTips' => $sumTotal - $sumUnitPrice
        ];
    }
}
