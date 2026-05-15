<?php

namespace App\Repository;

use App\Entity\Branch;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Branch>
 */
class BranchRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Branch::class);
    }

    public function countByCompany(int $companyId): int
    {
        return (int)$this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.company', 'c')
            ->where('c.id = :companyId')
            ->andWhere('b.deletedAt IS NULL')
            ->andWhere('b.isActive = true')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getAllToSelectByUser(int $userId): array
    {
        return $this->createQueryBuilder('u')
            ->select(['u.id', 'u.name'])
            ->innerJoin('App\Entity\UserBranch', 'ub', 'WITH', 'ub.branch = u.id')
            ->where('u.isActive = :active')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('ub.user = :userId')
            ->setParameter('active', true)
            ->setParameter('userId', $userId)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
