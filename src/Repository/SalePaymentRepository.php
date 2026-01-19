<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\SalePayment;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<SalePayment>
 */
class SalePaymentRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalePayment::class);
    }


}
