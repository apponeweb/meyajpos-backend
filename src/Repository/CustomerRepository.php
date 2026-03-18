<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Customer>
 */
class CustomerRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }
}
