<?php

namespace App\Repository;

use App\Entity\CashBoxMovement;
use App\Entity\CashBoxSession;
use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query;

/**
 * @extends BaseRepository<CashBoxMovement>
 */
class CashBoxMovementRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashBoxMovement::class);
    }

    public function getTotalOffsetBySession(CashBoxSession $session): float
    {
        // Sumamos Ingresos (1) y Egresos (2) en una sola consulta
        $qb = $this->createQueryBuilder('m')
            ->select('SUM(CASE WHEN m.type = 1 THEN m.amount ELSE 0 END) as totalIn')
            ->addSelect('SUM(CASE WHEN m.type = 2 THEN m.amount ELSE 0 END) as totalOut')
            ->where('m.cashBoxSession = :session')
            ->andWhere('m.isActive = :active')
            ->setParameter('session', $session)
            ->setParameter('active', true);

        $result = $qb->getQuery()->getOneOrNullResult();

        return (float)($result['totalIn'] ?? 0) - (float)($result['totalOut'] ?? 0);
    }

    public function getTotalWithdrawals(CashBoxSession $session): string
    {
        $qb = $this->createQueryBuilder('m')
            ->select('SUM(m.amount) as total')
            ->where('m.cashBoxSession = :session')
            ->andWhere('m.type = :type')
            ->setParameter('session', $session)
            ->setParameter('type', CashMovementType::EXTRACTION)
            ->getQuery()
            ->getOneOrNullResult();

        return $qb['total'] ?? '0.00';
    }
    public function getTotalDeposits(CashBoxSession $session): string
    {
        $qb = $this->createQueryBuilder('m')
            ->select('SUM(m.amount)')
            ->where('m.cashBoxSession = :session')
            ->andWhere('m.type = :type')
            ->andWhere('m.concept != :saleConcept')
            ->setParameter('session', $session)
            ->setParameter('type', CashMovementType::INCOME)
            ->setParameter('saleConcept', CashMovementConcept::SALE)
            ->getQuery();

        return $qb->getSingleScalarResult() ?? '0.00';
    }

    public function getWithPagination($search = null): Query
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.movementDate', 'DESC');

        // IMPORTANTE: Si enviamos la sesión en el array de búsqueda, filtramos por ella
        if (is_array($search)) {
            // Filtro obligatorio: Sesión activa
            if (!empty($search['session'])) {
                $qb->andWhere('m.cashBoxSession = :session')
                    ->setParameter('session', $search['session']);
            }

            // Filtros opcionales
            if (!empty($search['date'])) {
                $start = new \DateTime($search['date'] . ' 00:00:00');
                $end = new \DateTime($search['date'] . ' 23:59:59');
                $qb->andWhere('m.movementDate BETWEEN :start AND :end')
                    ->setParameter('start', $start)
                    ->setParameter('end', $end);
            }

            if (!empty($search['type'])) {
                $qb->andWhere('m.type = :type')
                    ->setParameter('type', $search['type']);
            }

            if (!empty($search['concept'])) {
                $qb->andWhere('m.concept = :concept')
                    ->setParameter('concept', $search['concept']);
            }
        }

        return $qb->getQuery();
    }
}
