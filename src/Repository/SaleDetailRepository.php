<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\MasterProduct;
use App\Entity\Sale;
use App\Entity\SaleDetail;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<SaleDetail>
 */
class SaleDetailRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaleDetail::class);
    }


}
