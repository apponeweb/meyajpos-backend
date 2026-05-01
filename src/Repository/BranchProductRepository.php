<?php

namespace App\Repository;

use App\Entity\BranchProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BranchProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BranchProduct::class);
    }

    public function findByBranch(int $branchId): array
    {
        return $this->createQueryBuilder('bp')
            ->join('bp.product', 'p')
            ->where('bp.branch = :branchId')
            ->setParameter('branchId', $branchId)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByBranchAndProduct(int $branchId, int $productId): ?BranchProduct
    {
        return $this->findOneBy(['branch' => $branchId, 'product' => $productId]);
    }

    public function getEnabledProductIdsByBranch(int $branchId): array
    {
        $rows = $this->createQueryBuilder('bp')
            ->select('IDENTITY(bp.product) as productId')
            ->where('bp.branch = :branchId')
            ->andWhere('bp.enabled = true')
            ->setParameter('branchId', $branchId)
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'productId');
    }
}
