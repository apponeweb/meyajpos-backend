<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Currency;
use App\Entity\PaymentType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<PaymentType>
 */
class PaymentTypeRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentType::class);
    }


}
