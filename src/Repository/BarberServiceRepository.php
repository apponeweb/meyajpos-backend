<?php

namespace App\Repository;

use App\Entity\BarberService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<BarberService>
 *
 * @method BarberService|null find($id, $lockMode = null, $lockVersion = null)
 * @method BarberService|null findOneBy(array $criteria, array $orderBy = null)
 * @method BarberService[]    findAll()
 * @method BarberService[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BarberServiceRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BarberService::class);
    }
}
