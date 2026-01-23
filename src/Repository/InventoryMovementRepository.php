<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Currency;
use App\Entity\InventoryMovement;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<InventoryMovement>
 */
class InventoryMovementRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryMovement::class);
    }


}
