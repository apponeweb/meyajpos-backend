<?php

namespace App\Repository;

use App\Entity\UserBranch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserBranchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBranch::class);
    }

    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('ub')
            ->join('ub.branch', 'b')
            ->where('ub.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ub.isDefault', 'DESC')
            ->addOrderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function userHasAccessToBranch(int $userId, int $branchId): bool
    {
        $result = $this->createQueryBuilder('ub')
            ->select('COUNT(ub.id)')
            ->where('ub.user = :userId')
            ->andWhere('ub.branch = :branchId')
            ->setParameter('userId', $userId)
            ->setParameter('branchId', $branchId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$result > 0;
    }
}
