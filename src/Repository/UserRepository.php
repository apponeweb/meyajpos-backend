<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\User;
use App\Entity\UserBranch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function getWithPagination($search = null, $branchId = null): Query
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('u.id', 'u.email', 'u.roles', 'u.name', 'u.enabled', 'u.phone', 'u.lastName', 'u.barberSn')
            ->leftJoin('u.commission', 'b')
            ->addSelect('b.id AS commission_id', 'b.name AS commission_name')
            ->leftJoin(UserBranch::class, 'ub', 'WITH', 'ub.user = u AND ub.isDefault = true')
            ->leftJoin('ub.branch', 'br')
            ->addSelect('br.name AS branch_name')
            ->orderBy('u.id', 'ASC');

        if ($search) {
            $queryBuilder->andWhere('u.name LIKE :val or u.email LIKE :val');
            $queryBuilder->setParameter('val', '%' . $search . '%');
        }

        if ($branchId) {
            $queryBuilder->andWhere('ub.branch = :branchId')
                ->setParameter('branchId', $branchId);
        }

        return $queryBuilder->getQuery();
    }


    public function getAllToSelect()
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('u.id', 'u.name')
            ->orderBy('u.name', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function findAllBarbers($excludeTimeOffToday = false): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u.id', 'u.name', 'u.lastName', 'p.photoUrl AS photoUrl')
            ->leftJoin('App\Entity\BarberProfile', 'p', 'WITH', 'p.user = u')
            ->where('u.barberSn = :barberSn')
            ->andWhere('u.enabled = :enabled')
            ->setParameter('enabled', true)
            ->setParameter('barberSn', true)
            ->orderBy('u.name', 'ASC');

        if ($excludeTimeOffToday) {
            $todayStart = new \DateTime('today 00:00:00');
            $todayEnd = new \DateTime('today 23:59:59');

            $qb->leftJoin('App\Entity\BarberTimeOff', 't', 'WITH', 't.barber = u AND (t.startAtLocal <= :todayEnd AND t.endAtLocal >= :todayStart)')
               ->andWhere('t.id IS NULL')
               ->setParameter('todayStart', $todayStart)
               ->setParameter('todayEnd', $todayEnd);
        }

        return $qb->getQuery()->getResult();
    }

    public function getBarbersWithPagination(?string $search = null, ?string $classification = null, ?string $experience = null, $branchId = null)
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u.id, u.name, u.lastName, u.email, u.phone, u.enabled', 'p.photoUrl', 'p.avgRating', 'p.ratingCount', 'p.slotMinutes', 'p.classification', 'p.experience')
            ->addSelect('br.name AS branch_name')
            ->leftJoin('App\Entity\BarberProfile', 'p', 'WITH', 'p.user = u')
            ->leftJoin(UserBranch::class, 'ub', 'WITH', 'ub.user = u AND ub.isDefault = true')
            ->leftJoin('ub.branch', 'br')
            ->andWhere('u.barberSn = :isBarber')
            ->setParameter('isBarber', true);

        if ($search) {
            $qb->andWhere('u.name LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search OR CONCAT(u.name, \' \', u.lastName) LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($classification) {
            $qb->andWhere('p.classification LIKE :classification')
                ->setParameter('classification', '%' . $classification . '%');
        }

        if ($experience) {
            $qb->andWhere('p.experience LIKE :experience')
                ->setParameter('experience', '%' . $experience . '%');
        }

        if ($branchId) {
            $qb->andWhere('ub.branch = :branchId')
                ->setParameter('branchId', $branchId);
        }

        return $qb;
    }

    public function getAllBarbersToSelect()
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('u.id', 'u.name')
            ->addSelect('p.photoUrl')
            ->leftJoin('u.profile', 'p')
            ->andWhere('u.barberSn = :isBarber')
            ->setParameter('isBarber', true)
            ->orderBy('u.name', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }
}
