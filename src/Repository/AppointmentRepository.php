<?php

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Appointment>
 */
class AppointmentRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }
}
