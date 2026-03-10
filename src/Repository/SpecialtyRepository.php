<?php

namespace App\Repository;

use App\Entity\Specialty;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Specialty>
 *
 * @method Specialty|null find($id, $lockMode = null, $lockVersion = null)
 * @method Specialty|null findOneBy(array $criteria, array $orderBy = null)
 * @method Specialty[]    findAll()
 * @method Specialty[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpecialtyRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Specialty::class);
    }

    protected function getDefaultFields(): array
    {
        return ['u.id', 'u.name', 'u.isActive'];
    }
}
