<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\InventoryMovementDetail;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<InventoryMovementDetail>
 */
class InventoryMovementDetailRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryMovementDetail::class);
    }


}
