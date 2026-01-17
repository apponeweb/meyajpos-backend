<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\MasterProduct;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<MasterProduct>
 */
class MasterProductRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MasterProduct::class);
    }


}
