<?php

namespace App\License\Repository;

use App\Entity\User;
use App\License\Entity\LicLicense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LicLicense>
 */
class LicLicenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LicLicense::class);
    }

    public function findActiveByUser(User $user): ?LicLicense
    {
        $today = new \DateTime('today');
        
        return $this->createQueryBuilder('l')
            ->join('l.user', 'u')
            ->where('u.id = :userId')
            ->andWhere('l.isActive = :active')
            ->andWhere('l.expiresAt IS NULL OR l.expiresAt >= :today')
            ->setParameter('userId', $user->getId())
            ->setParameter('active', true)
            ->setParameter('today', $today)
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findWithDetails(int $id): ?LicLicense
    {
        return $this->createQueryBuilder('l')
            ->join('l.user', 'u')
            ->join('l.company', 'c')
            ->leftJoin('l.licenseSystems', 'ls')
            ->leftJoin('ls.system', 's')
            ->addSelect('u', 'c', 'ls', 's')
            ->where('l.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getListQueryBuilder(?string $search = null, ?string $status = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->join('l.user', 'u')
            ->join('l.company', 'c')
            ->leftJoin('l.licenseSystems', 'ls')
            ->leftJoin('ls.system', 's')
            ->addSelect('u', 'c', 'ls', 's')
            ->orderBy('l.id', 'DESC');

        if ($search) {
            $qb->andWhere('u.name LIKE :val OR u.email LIKE :val OR c.name LIKE :val')
                ->setParameter('val', '%' . $search . '%');
        }

        if ($status === 'expired') {
            $qb->andWhere('l.expiresAt < :today')
                ->setParameter('today', new \DateTime('today'));
        } elseif ($status === 'active') {
            $qb->andWhere('l.expiresAt >= :today')
                ->setParameter('today', new \DateTime('today'));
        } elseif ($status === 'expiring') {
            $qb->andWhere('l.expiresAt >= :today AND l.expiresAt <= :week')
                ->setParameter('today', new \DateTime('today'))
                ->setParameter('week', new \DateTime('+7 days'));
        }

        return $qb;
    }

    public function getDashboardStats(): array
    {
        $today = new \DateTime('today');
        $nextWeek = new \DateTime('+7 days');

        $total = (int)$this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.isActive = true')
            ->getQuery()->getSingleScalarResult();

        $active = (int)$this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.isActive = true AND l.expiresAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()->getSingleScalarResult();

        $expired = (int)$this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.isActive = true AND l.expiresAt < :today')
            ->setParameter('today', $today)
            ->getQuery()->getSingleScalarResult();

        $expiringSoon = (int)$this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.isActive = true AND l.expiresAt >= :today AND l.expiresAt <= :week')
            ->setParameter('today', $today)
            ->setParameter('week', $nextWeek)
            ->getQuery()->getSingleScalarResult();

        $totalPending = (int)$this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.isActive = true AND l.activatedAt IS NULL')
            ->getQuery()->getSingleScalarResult();

        $totalActivated = (int)$this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.isActive = true AND l.activatedAt IS NOT NULL')
            ->getQuery()->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'expiringSoon' => $expiringSoon,
            'totalPending' => $totalPending,
            'totalActivated' => $totalActivated,
        ];
    }
}
