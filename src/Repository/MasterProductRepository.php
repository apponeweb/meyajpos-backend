<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\MasterProduct;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<MasterProduct>
 */
class MasterProductRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MasterProduct::class);
    }

    public function findDetailsByBarcode(string $barcode): ?array
    {
        return $this->createQueryBuilder('m')
            ->select('m.id', 'm.name', 'm.price', 'st.isCourtesy')
            ->join('m.serviceType', 'st')
            ->where('m.barcode = :barcode')
            ->andWhere('m.isActive = :active')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('barcode', $barcode)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
