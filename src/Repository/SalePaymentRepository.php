<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\CashBoxSession;
use App\Entity\PaymentType;
use App\Entity\SalePayment;
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
}
