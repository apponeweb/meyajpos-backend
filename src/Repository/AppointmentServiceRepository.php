<?php

namespace App\Repository;

use App\Entity\AppointmentService;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<AppointmentService>
 */
class AppointmentServiceRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppointmentService::class);
    }

    /**
     * Checks if a barber has any overlapping appointments for a given time range.
     * 
     * @param int $barberId
     * @param \DateTimeInterface $start
     * @param int $durationMinutes
     * @return bool True if there is an overlap, false otherwise.
     */
    public function hasOverlap(int $barberId, \DateTimeInterface $start, int $durationMinutes): bool
    {
        $end = (clone $start)->modify('+' . $durationMinutes . ' minutes');

        // Fetch all appointments for this barber on this specific day to check for overlaps
        // This avoids relying on DQL DATE_ADD extension which might not be installed.
        $dayStart = (clone $start)->setTime(0, 0, 0);
        $dayEnd = (clone $start)->setTime(23, 59, 59);

        $appointments = $this->createQueryBuilder('asv')
            ->where('asv.barber = :barberId')
            ->andWhere('asv.deletedAt IS NULL')
            ->andWhere('asv.scheduledDateTime BETWEEN :dayStart AND :dayEnd')
            ->setParameter('barberId', $barberId)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->getQuery()
            ->getResult();

        /** @var AppointmentService $app */
        foreach ($appointments as $app) {
            $appStart = $app->getScheduledDateTime();
            $appEnd = (clone $appStart)->modify('+' . $app->getDuration() . ' minutes');

            // Overlap logic: (start1 < end2) AND (end1 > start2)
            if ($start < $appEnd && $end > $appStart) {
                return true;
            }
        }

        return false;
    }
}
