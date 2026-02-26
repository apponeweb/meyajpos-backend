<?php

namespace App\Repository;

use App\Entity\SaleAuditDeleted;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SaleAuditDeletedRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleAuditDeleted::class);
    }

    // src/Repository/SaleAuditDeletedRepository.php

    public function getAuditQuery(array $filters)
    {
        $qb = $this->createQueryBuilder('a');

        if (!empty($filters['startDate'])) {
            $qb->andWhere('a.deletedAt >= :start')
                ->setParameter('start', new \DateTime($filters['startDate'] . ' 00:00:00'));
        }

        if (!empty($filters['endDate'])) {
            $qb->andWhere('a.deletedAt <= :end')
                ->setParameter('end', new \DateTime($filters['endDate'] . ' 23:59:59'));
        }

        // Filtro por Usuario Original (ID)
        if (!empty($filters['originalUserId'])) {
            // En Doctrine, para buscar dentro de JSON de forma simple sin extensiones:
            $qb->andWhere('a.content LIKE :userId')
                ->setParameter('userId', '%"user":' . $filters['originalUserId'] . '%');
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('a.folio LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('a.deletedAt', 'DESC')->getQuery();
    }

    public function getAuditTotals(array $filters): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('SUM(a.total) as totalAmount');

        // Aplicar mismos filtros que en getAuditQuery...
        // (Puedes extraer la lógica de filtros a un método privado para reutilizarla)

        return $qb->getQuery()->getSingleResult();
    }
}
