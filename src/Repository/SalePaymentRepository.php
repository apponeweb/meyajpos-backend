<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\CashBoxSession;
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

}
