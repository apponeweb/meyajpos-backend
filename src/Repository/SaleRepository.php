<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\MasterProduct;
use App\Entity\Sale;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Sale>
 */
class SaleRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }


}
