<?php

namespace App\Repository;

use App\Entity\User;
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

    public function getWithPagination($search = null): Query
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('u.id', 'u.email', 'u.roles', 'u.name', 'u.enabled', 'u.phone', 'u.name', 'u.lastName', 'u.barberSn')
            ->leftJoin('u.commission', 'b')
            ->addSelect('b.id AS commission_id', 'b.name AS commission_name')
            ->orderBy('u.id', 'ASC');

        if ($search) {
            $queryBuilder->andWhere('u.name LIKE :val or u.email LIKE :val');
            $queryBuilder->setParameter('val', '%' . $search . '%');
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

    public function findAllBarbers(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id', 'u.name', 'u.lastName', 'p.photoUrl')
            ->leftJoin('App\Entity\BarberProfile', 'p', 'WITH', 'p.user = u')
            ->where('u.barberSn = :barberSn')
            ->andWhere('u.enabled = :enabled')
            ->setParameter('enabled', true)
            ->setParameter('barberSn', true)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getBarbersWithPagination(?string $search = null, ?string $classification = null, ?string $experience = null)
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u.id, u.name, u.lastName, u.email, u.phone, u.enabled', 'p.photoUrl', 'p.avgRating', 'p.ratingCount', 'p.slotMinutes', 'p.classification', 'p.experience')
            ->leftJoin('App\Entity\BarberProfile', 'p', 'WITH', 'p.user = u')
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

        return $qb;
    }

    public function getAllBarbersToSelect()
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('u.id', 'u.name')
            ->andWhere('u.barberSn = :isBarber')
            ->setParameter('isBarber', true)
            ->orderBy('u.name', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }
}
