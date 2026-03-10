<?php

namespace App\Repository;

use App\Entity\BarberSpecialty;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<BarberSpecialty>
 *
 * @method BarberSpecialty|null find($id, $lockMode = null, $lockVersion = null)
 * @method BarberSpecialty|null findOneBy(array $criteria, array $orderBy = null)
 * @method BarberSpecialty[]    findAll()
 * @method BarberSpecialty[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BarberSpecialtyRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BarberSpecialty::class);
    }
}
