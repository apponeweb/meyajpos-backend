<?php

namespace App\Repository;

use App\Entity\InventoryStock;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<InventoryStock>
 */
class InventoryStockRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryStock::class);
    }


}
