<?php

namespace App\Repository;

use App\Entity\ZReportDetail;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<ZReportDetail>
 */
class ZReportDetailRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZReportDetail::class);
    }


}
