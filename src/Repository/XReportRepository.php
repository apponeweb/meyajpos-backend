<?php

namespace App\Repository;

use App\Entity\XReport;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<XReport>
 */
class XReportRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, XReport::class);
    }


}
