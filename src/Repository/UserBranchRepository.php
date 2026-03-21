<?php

namespace App\Repository;

use App\Entity\UserBranch;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBranch>
 */
class UserBranchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBranch::class);
    }

    /**
     * Obtiene todas las sucursales asignadas a un usuario con información de empresa
     */
    public function findBranchesWithCompanyByUser(User $user): array
    {
        return $this->createQueryBuilder('ub')
            ->select('ub', 'b', 'c')
            ->join('ub.branch', 'b')
            ->join('b.company', 'c')
            ->where('ub.user = :user')
            ->andWhere('b.isActive = true')
            ->andWhere('c.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene la sucursal por defecto del usuario
     */
    public function findDefaultBranch(User $user): ?UserBranch
    {
        return $this->findOneBy([
            'user' => $user,
            'isDefault' => true
        ]);
    }

    /**
     * Verifica si el usuario tiene acceso a una sucursal específica
     */
    public function userHasAccessToBranch(User $user, int $branchId): bool
    {
        $result = $this->createQueryBuilder('ub')
            ->select('COUNT(ub.id)')
            ->join('ub.branch', 'b')
            ->where('ub.user = :user')
            ->andWhere('b.id = :branchId')
            ->andWhere('b.isActive = true')
            ->setParameter('user', $user)
            ->setParameter('branchId', $branchId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }
}
