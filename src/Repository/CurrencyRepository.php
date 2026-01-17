<?php

namespace App\Repository;

use App\Entity\Branch;
use App\Entity\Currency;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Currency>
 */
class CurrencyRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Currency::class);
    }


}
