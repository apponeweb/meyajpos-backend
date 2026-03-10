<?php

namespace App\Repository;

use App\Entity\BarberSchedule;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<BarberSchedule>
 */
class BarberScheduleRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BarberSchedule::class);
    }

    /**
     * Finds overlapping schedules for a barber across all branches.
     */
    public function findOverlappingSchedules(
        int $barberId,
        int $dayOfWeek,
        \DateTimeInterface $openTime,
        \DateTimeInterface $closeTime,
        \DateTimeInterface $validFrom,
        ?\DateTimeInterface $validTo = null,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->where('s.barber = :barberId')
            ->andWhere('s.dayOfWeek = :dayOfWeek')
            ->andWhere('s.openTime < :closeTime')
            ->andWhere('s.closeTime > :openTime')
            // Date range overlap logic:
            // (s.validFrom <= :validTo OR :validTo IS NULL) AND (s.validTo >= :validFrom OR s.validTo IS NULL)
            ->andWhere('(:validTo IS NULL OR s.validFrom <= :validTo)')
            ->andWhere('(s.validTo IS NULL OR s.validTo >= :validFrom)')
            ->setParameter('barberId', $barberId)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('openTime', $openTime)
            ->setParameter('closeTime', $closeTime)
            ->setParameter('validFrom', $validFrom)
            ->setParameter('validTo', $validTo)
            ->andWhere('s.deletedAt IS NULL');

        if ($excludeId) {
            $qb->andWhere('s.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }
}
