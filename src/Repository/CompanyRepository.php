<?php

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Company>
 */
class CompanyRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }


}
