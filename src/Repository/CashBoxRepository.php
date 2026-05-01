<?php

namespace App\Repository;

use App\Entity\CashBox;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<CashBox>
 */
class CashBoxRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashBox::class);
    }


    public function getAllToSelectByBranch(int $branchId): array
    {
        return $this->createQueryBuilder('u')
            ->select(['u.id', 'u.name'])
            ->where('u.isActive = :active')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('u.branch = :branchId')
            ->setParameter('active', true)
            ->setParameter('branchId', $branchId)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
