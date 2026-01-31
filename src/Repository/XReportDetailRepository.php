<?php

namespace App\Repository;

use App\Entity\XReportDetail;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<XReportDetail>
 */
class XReportDetailRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, XReportDetail::class);
    }


}
