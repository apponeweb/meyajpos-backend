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
            ->select('u.id', 'u.email', 'u.roles', 'u.name', 'u.enabled', 'u.phone', 'u.name', 'u.lastName')
            ->leftJoin('u.branch', 'b')
            ->addSelect('b.id AS branch_id', 'b.name AS branch_name')
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


}
