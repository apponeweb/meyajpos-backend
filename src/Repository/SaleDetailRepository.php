<?php

namespace App\Repository;

use App\Entity\SaleDetail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

class SaleDetailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleDetail::class);
    }

    /**
     * Consulta principal para el reporte de detalles (paginado)
     */
    public function getDetailsReportQuery(array $filters): Query
    {
        $qb = $this->createQueryBuilder('sd')
            ->select('sd', 's', 'p', 'st', 'u')
            ->innerJoin('sd.sale', 's')
            ->innerJoin('sd.product', 'p')
            ->leftJoin('p.serviceType', 'st')     // Aquí se define 'st'
            ->leftJoin('sd.serviceProvider', 'u'); // Aquí se define 'u'

        $this->applyFilters($qb, $filters);

        $qb->orderBy('s.saleDate', 'DESC');

        return $qb->getQuery();
    }

    /**
     * Totales para la fila amarilla inferior
     */
    public function getDetailsTotalAccumulated(array $filters): array
    {
        $qb = $this->createQueryBuilder('sd')
            ->select(
                'SUM(sd.quantity) as sumQuantity',
                'SUM(sd.total) as sumTotal',
                'SUM(sd.total - sd.unitPrice) as sumTips'
            )
            ->innerJoin('sd.sale', 's')
            ->innerJoin('sd.product', 'p')
            // AGREGAR ESTOS JOINS PARA QUE EL FILTRO TENGA ACCESO A 'st' y 'u'
            ->leftJoin('p.serviceType', 'st')
            ->leftJoin('sd.serviceProvider', 'u');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Obtener datos para exportar a CSV
     */
    public function getDetailsExportData(array $filters): array
    {
        $qb = $this->createQueryBuilder('sd')
            ->select(
                's.folio as saleFolio',
                'p.name as productName',
                'st.name as serviceType',
                'u.name as barberName',
                'sd.quantity',
                'sd.unitPrice',
                'sd.total',
                's.saleDate'
            )
            ->innerJoin('sd.sale', 's')
            ->innerJoin('sd.product', 'p')
            ->leftJoin('p.serviceType', 'st')
            ->leftJoin('sd.serviceProvider', 'u');

        $this->applyFilters($qb, $filters);

        $qb->orderBy('s.saleDate', 'DESC');

        return $qb->getQuery()->getScalarResult(); // Importante: ScalarResult para obtener array plano
    }

    /**
     * Filtros compartidos entre el reporte y los totales
     */
    private function applyFilters($qb, array $filters): void
    {
        // Filtro por Rango de Fecha (usa saleDate de la entidad Sale)
        if (!empty($filters['startDate'])) {
            $qb->andWhere('s.saleDate >= :startDate')
                ->setParameter('startDate', new \DateTime($filters['startDate'] . ' 00:00:00'));
        }

        if (!empty($filters['endDate'])) {
            $qb->andWhere('s.saleDate <= :endDate')
                ->setParameter('endDate', new \DateTime($filters['endDate'] . ' 23:59:59'));
        }

        // Filtro por Barbero (ID del User serviceProvider)
        if (!empty($filters['barberId'])) {
            $qb->andWhere('u.id = :barberId')
                ->setParameter('barberId', $filters['barberId']);
        }

        // Filtro por Tipo de Servicio (ID de ServiceType en el MasterProduct)
        if (!empty($filters['serviceTypeId'])) {
            $qb->andWhere('st.id = :serviceTypeId')
                ->setParameter('serviceTypeId', $filters['serviceTypeId']);
        }

        // Búsqueda general (Folio, Producto o Barbero)
        if (!empty($filters['search'])) {
            $qb->andWhere('s.folio LIKE :search OR p.name LIKE :search OR u.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }
    }
}
