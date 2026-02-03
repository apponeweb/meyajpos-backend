<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\CashBoxSession;
use App\Entity\Tip;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Tip>
 */
class TipRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tip::class);
    }

    /**
     * Suma todas las propinas registradas en una sesión de caja específica
     */
    public function getTotalTipsBySession(CashBoxSession $session): string
    {
        // Usamos el QueryBuilder para navegar por las relaciones
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            // Unimos con SalePayment
            ->innerJoin('t.salePayment', 'sp')
            // Unimos con Sale (suponiendo que SalePayment tiene la relación a Sale)
            ->innerJoin('sp.sale', 's')
            ->where('s.cashBox = :session')
            ->andWhere('t.isActive = :active')
            ->setParameter('session', $session->getCashBox())
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }
}
