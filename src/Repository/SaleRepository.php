<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\CashBoxSession;
use App\Entity\MasterProduct;
use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Enum\SaleStatus;
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

        if (!empty($filters['branch'])) {
            $qb->leftJoin('cb.branch', 'br')
               ->andWhere('br.id = :branchId')
               ->setParameter('branchId', $filters['branch']);
        }

        $qb->orderBy('s.id', 'DESC');

        return $qb->getQuery();
    }

    public function getExportData(array $filters): array
    {
        // Reutilizamos la consulta base
        $query = $this->getReportQuery($filters);

        // Obtenemos los resultados como un array escalar para procesar más rápido
        return $query->getScalarResult();
    }

    public function getTotalAccumulated(array $filters): array
    {
        $qb = $this->createQueryBuilder('s')
            // Sumamos el total y el cambio de forma independiente
            ->select('SUM(s.total) as totalSales, SUM(s.change) as totalChange')
            ->andWhere('s.status = :paidStatus')
            ->setParameter('paidStatus', SaleStatus::PAID->value);

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

    /**
     * Devuelve la suma de ventas de una sesión específica filtrada por un método de pago.
     * Se consulta a través de SaleDetail para obtener precisión por PaymentType.
     */
    public function getSummaryByPaymentType(CashBoxSession $session, int $paymentType): string
    {
        $now = new \DateTime();

        $qb = $this->createQueryBuilder('s')
            ->select('SUM(sd.amountReceived) - SUM(s.change)  as totalAmount')
            ->innerJoin('s.payments', 'sd')
            ->where('s.cashBoxSession = :cashBoxSession')
            ->andWhere('s.status = :paidStatus')
            ->andWhere('sd.paymentType = :paymentType')
            ->andWhere('s.createdAt >= :openedAt')
            ->andWhere('s.createdAt <= :now')
            ->setParameter('cashBoxSession', $session->getId())
            ->setParameter('paidStatus', SaleStatus::PAID->value)
            ->setParameter('paymentType', $paymentType)
            ->setParameter('openedAt', $session->getOpeningDate())
            ->setParameter('now', $now);

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result['totalAmount'] ?? '0.00';
    }

    public function getTotalCashSalesBySession(CashBoxSession $session): string
    {
        return $this->createQueryBuilder('s')
            ->select('SUM(s.subtotal)') // O el campo donde guardes el subtotal pagado en efectivo
            ->where('s.cashBox = :cashBox')
            // Si tienes múltiples métodos de pago, aquí filtrarías solo por efectivo
            ->setParameter('cashBox', $session->getCashBox())
            ->getQuery()
            ->getSingleScalarResult() ?? '0.00';
    }


    public function getCountByPaymentType(CashBoxSession $session, int $paymentTypeId): int
    {
        return (int)$this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.cashBox = :session')
            ->andWhere('s.paymentType = :paymentType')
            ->andWhere('s.isActive = :active')
            ->setParameter('session', $session->getCashBox())
            ->setParameter('paymentType', $paymentTypeId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Suma el total de propinas de la sesión.
     */
    public function getTotalTipsBySession(CashBoxSession $session): string
    {
        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.tipAmount)') // Ajusta según el nombre real de tu campo en Sale
            ->where('s.cashBox = :session')
            ->andWhere('s.isActive = :active')
            ->setParameter('session', $session->getCashBox())
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }


}
