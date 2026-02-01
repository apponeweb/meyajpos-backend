<?php

namespace App\Repository;

use App\Entity\ZReport;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<ZReport>
 */
class ZReportRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZReport::class);
    }


}
