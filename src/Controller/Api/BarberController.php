<?php

namespace App\Controller\Api;

use App\Entity\AppointmentService;
use App\Entity\BarberProfile;
use App\Entity\BarberSchedule;
use App\Entity\BarberService;
use App\Entity\BarberSpecialty;
use App\Entity\BarberTimeOff;
use App\Entity\BranchHour;
use App\Entity\MasterProduct;
use App\Entity\Sale;
use App\Entity\User;
use App\Enum\SaleStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class BarberController extends BaseController
{
    protected function getEntityClass(): string
    {
        return User::class;
    }

    protected function getFormTypeClass(): string
    {
        return ''; // No form for this controller yet
    }

    #[Rest\Get('/barbers/export')]
    public function exportBarbersCsv(Request $request, UserRepository $userRepository): Response
    {
        try {
            $search = $request->query->get('search');
            $classification = $request->query->get('classification');
            $experience = $request->query->get('experience');
            $branch = $request->query->get('branch');

            $data = $userRepository->getBarbersWithPagination($search, $classification, $experience, $branch)->getQuery()->getResult();

            $response = new StreamedResponse(function () use ($data) {
                $handle = fopen('php://output', 'w+');
                // UTF-8 BOM for Excel
                fwrite($handle, "\xEF\xBB\xBF");

                // Headers
                fputcsv($handle, [
                    'ID',
                    'NOMBRE',
                    'APELLIDO',
                    'CORREO',
                    'TELÉFONO',
                    'ROL/CLASIFICACIÓN',
                    'EXPERIENCIA',
                    'RATING',
                    'RESEÑAS',
                    'ESTADO'
                ], ';');

                foreach ($data as $row) {
                    fputcsv($handle, [
                        $row['id'],
                        $row['name'],
                        $row['lastName'],
                        $row['email'],
                        $row['phone'],
                        $row['classification'] ?? '',
                        $row['experience'] ?? '',
                        number_format((float)($row['avgRating'] ?? 0), 2, '.', ''),
                        $row['ratingCount'] ?? 0,
                        ($row['enabled'] ?? true) ? 'Activo' : 'Inactivo'
                    ], ';');
                }
                fclose($handle);
            });

            $fileName = 'reporte_barberos_' . date('Ymd_His') . '.csv';
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

            return $response;

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al exportar barberos',
                'detail' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    #[Rest\Get('/barbers')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function getBarbersPaginated(Request $request, UserRepository $userRepository): JsonResponse
    {
        try {
            $search = $request->query->get('search');
            $classification = $request->query->get('classification');
            $experience = $request->query->get('experience');
            $branch = $request->query->get('branch');
            $current = $request->query->getInt('current', 1);
            $pageSize = $request->query->getInt('pageSize', 10);

            $qb = $userRepository->getBarbersWithPagination($search, $classification, $experience, $branch);

            // 1. Conteo eficiente (clonando el QueryBuilder)
            $countQb = clone $qb;
            $total = (int)$countQb->select('COUNT(DISTINCT u.id)')->getQuery()->getSingleScalarResult();

            // 2. Paginación y ejecución
            $qb->orderBy('u.id', 'ASC')
                ->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            return $this->json([
                'total' => $total,
                'results' => $qb->getQuery()->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY),
                'current' => $current,
                'pageSize' => $pageSize,
                'status' => Response::HTTP_OK
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al obtener el listado de barberos',
                'detail' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'status' => 500
            ], 500);
        }
    }


    #[Rest\Get('/barbers/all')]
    public function all(Request $request, UserRepository $userRepository): JsonResponse
    {
        $barbers = $userRepository->getAllBarbersToSelect();
        $baseUrl = $request->getSchemeAndHttpHost();

        $result = array_map(function ($barber) use ($baseUrl) {
            $barber['photoUrl'] = !empty($barber['photoUrl'])
                ? $baseUrl . $barber['photoUrl']
                : null;
            return $barber;
        }, $barbers);

        return $this->json($result);
    }

    #[Rest\Get('/barber/available-list')]
    public function getAvailableList(Request $request, UserRepository $userRepository): JsonResponse
    {
        $branchId = $request->query->get('branchId');
        $dateStr = $request->query->get('date');
        $productId = $request->query->get('productId');

        if (!$branchId || !$dateStr || !$productId) {
            return $this->json([
                'message' => 'Faltan parámetros requeridos: branchId, date, productId'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $date = new \DateTime($dateStr);
            $dayOfWeek = (int)$date->format('N');
        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Formato de fecha inválido. Use YYYY-MM-DD'
            ], Response::HTTP_BAD_REQUEST);
        }

        // 1. Check if Branch is open on that day
        $branchHour = $this->entityManager->getRepository(BranchHour::class)
            ->createQueryBuilder('bh')
            ->where('bh.branch = :branchId')
            ->andWhere('bh.dayOfWeek = :dayOfWeek')
            ->andWhere('bh.validFrom <= :date')
            ->andWhere('(bh.validTo IS NULL OR bh.validTo >= :date)')
            ->setParameter('branchId', $branchId)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$branchHour) {
            return $this->json([], Response::HTTP_OK); // Branch closed
        }

        // 2. Find Barbers that:
        //    - Have the product (BarberService)
        //    - Have a schedule in this branch on this day (BarberSchedule)
        //    - The schedule is valid for this date
        $qb = $userRepository->createQueryBuilder('u')
            ->select('u.id, u.name, u.lastName', 'p.photoUrl', 'p.avgRating', 'p.ratingCount', 'p.classification', 'p.experience')
            ->addSelect('bsched.openTime, bsched.closeTime')
            ->leftJoin(BarberProfile::class, 'p', 'WITH', 'p.user = u.id')
            ->join(BarberService::class, 'bserv', 'WITH', 'bserv.barber = u.id')
            ->join(BarberSchedule::class, 'bsched', 'WITH', 'bsched.barber = u.id')
            ->where('u.barberSn = :isBarber')
            ->andWhere('u.enabled = :enabled')
            ->andWhere('bserv.product = :productId')
            ->andWhere('bserv.isActive = :activeService')
            ->andWhere('bsched.branch = :branchId')
            ->andWhere('bsched.dayOfWeek = :dayOfWeek')
            ->andWhere('bsched.validFrom <= :date')
            ->andWhere('(bsched.validTo IS NULL OR bsched.validTo >= :date)')
            ->setParameter('isBarber', true)
            ->setParameter('enabled', true)
            ->setParameter('productId', $productId)
            ->setParameter('activeService', true)
            ->setParameter('branchId', $branchId)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('date', $date->format('Y-m-d'));

        $potentialBarbers = $qb->getQuery()->getResult();

        $scheme = $request->getScheme();
        $host = $request->getHttpHost();
        $baseUrl = $scheme . '://' . $host;

        $result = [];
        $addedBarberIds = [];
        foreach ($potentialBarbers as $barberData) {
            $barberId = $barberData['id'];
            if (in_array($barberId, $addedBarberIds)) {
                continue;
            }

            // 3. Check for Time Off ...
            $shiftStart = clone $date;
            $shiftStart->setTime((int)$barberData['openTime']->format('H'), (int)$barberData['openTime']->format('i'), (int)$barberData['openTime']->format('s'));

            $shiftEnd = clone $date;
            $shiftEnd->setTime((int)$barberData['closeTime']->format('H'), (int)$barberData['closeTime']->format('i'), (int)$barberData['closeTime']->format('s'));

            $timeOffOverlap = $this->entityManager->getRepository(BarberTimeOff::class)
                ->createQueryBuilder('to')
                ->where('to.barber = :barberId')
                ->andWhere('to.branch = :branchId OR to.branch IS NULL')
                ->andWhere(':shiftStart < to.endAtLocal AND :shiftEnd > to.startAtLocal')
                ->setParameter('barberId', $barberId)
                ->setParameter('branchId', $branchId)
                ->setParameter('shiftStart', $shiftStart)
                ->setParameter('shiftEnd', $shiftEnd)
                ->getQuery()
                ->getResult();

            if (!empty($timeOffOverlap)) {
                continue; // Barber has an interference
            }

            // Get specialties
            $specialtiesList = $this->entityManager->getRepository(BarberSpecialty::class)
                ->createQueryBuilder('bsm')
                ->select('s.name')
                ->join('bsm.specialty', 's')
                ->where('bsm.barber = :barberId')
                ->setParameter('barberId', $barberId)
                ->getQuery()
                ->getResult();

            $specialties = array_map(fn($s) => $s['name'], $specialtiesList);

            $result[] = [
                'id' => $barberId,
                'name' => $barberData['name'] . ($barberData['lastName'] ? ' ' . $barberData['lastName'] : ''),
                'role' => $barberData['classification'] ?? '',
                'experience' => (!empty($barberData['experience']) && $barberData['experience'] > 0)
                    ? $barberData['experience'] . ($barberData['experience'] == 1 ? ' año de experiencia' : ' años de experiencia')
                    : null,
                'rating' => (float)($barberData['avgRating'] ?? 0),
                'reviewCount' => (int)($barberData['ratingCount'] ?? 0),
                'image' => $barberData['photoUrl'] ? $baseUrl . $barberData['photoUrl'] : null,
                'specialties' => $specialties,
            ];
            $addedBarberIds[] = $barberId;
        }

        return $this->json($result, Response::HTTP_OK);
    }

    #[Rest\Get('/barber/schedule')]
    public function getAvailableSlots(Request $request): JsonResponse
    {
        $barberId = $request->query->get('barberId');
        $branchId = $request->query->get('branchId');
        $dateStr = $request->query->get('date');
        $productId = $request->query->get('productId');

        if (!$branchId || !$dateStr || !$productId) {
            return $this->json([
                'message' => 'Faltan parámetros requeridos: branchId, date, productId'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $date = new \DateTime($dateStr);
            $dayOfWeek = (int)$date->format('N');
        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Formato de fecha inválido. Use YYYY-MM-DD'
            ], Response::HTTP_BAD_REQUEST);
        }

        // 1. Check if Branch is open on that day
        $branchHourOpen = $this->entityManager->getRepository(BranchHour::class)
            ->createQueryBuilder('bh')
            ->where('bh.branch = :branchId')
            ->andWhere('bh.dayOfWeek = :dayOfWeek')
            ->andWhere('bh.validFrom <= :date')
            ->andWhere('(bh.validTo IS NULL OR bh.validTo >= :date)')
            ->setParameter('branchId', $branchId)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$branchHourOpen) {
            return $this->json([], Response::HTTP_OK); // Branch closed
        }

        // Si no mandan barbero, retornamos el horario general de la sucursal
        if (!$barberId) {
            $slots = [];
            $currentTime = clone $date;
            $currentTime->setTime((int)$branchHourOpen->getOpenTime()->format('H'), (int)$branchHourOpen->getOpenTime()->format('i'));

            $endTime = clone $date;
            $endTime->setTime((int)$branchHourOpen->getCloseTime()->format('H'), (int)$branchHourOpen->getCloseTime()->format('i'));

            while ($currentTime < $endTime) {
                $slots[] = $currentTime->format('h:i A');
                $currentTime->modify("+30 minutes");
            }

            $groups = [
                ['id' => 'Mañana', 'icon' => 'Sun', 'times' => []],
                ['id' => 'Tarde', 'icon' => 'CloudSun', 'times' => []],
                ['id' => 'Noche', 'icon' => 'Moon', 'times' => []],
            ];

            foreach ($slots as $timeStr) {
                $time = \DateTime::createFromFormat('h:i A', $timeStr);
                $hour = (int)$time->format('H');

                if ($hour < 12) {
                    $groups[0]['times'][] = $timeStr;
                } elseif ($hour < 17) {
                    $groups[1]['times'][] = $timeStr;
                } else {
                    $groups[2]['times'][] = $timeStr;
                }
            }

            $result = array_values(array_filter($groups, fn($g) => !empty($g['times'])));
            return $this->json($result, Response::HTTP_OK);
        }

        // 2. Check if Barber provides the product
        $barberService = $this->entityManager->getRepository(BarberService::class)->findOneBy([
            'barber' => $barberId,
            'product' => $productId,
            'isActive' => true
        ]);

        if (!$barberService) {
            return $this->json([], Response::HTTP_OK); // Barber doesn't offer the product
        }

        // 3. Get Barber Schedule
        $barberSchedule = $this->entityManager->getRepository(BarberSchedule::class)
            ->createQueryBuilder('bs')
            ->where('bs.barber = :barberId')
            ->andWhere('bs.branch = :branchId')
            ->andWhere('bs.dayOfWeek = :dayOfWeek')
            ->andWhere('bs.validFrom <= :date')
            ->andWhere('(bs.validTo IS NULL OR bs.validTo >= :date)')
            ->setParameter('barberId', $barberId)
            ->setParameter('branchId', $branchId)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$barberSchedule) {
            return $this->json([], Response::HTTP_OK);
        }

        // 2. Get Barber Profile for slot configuration
        $barberProfile = $this->entityManager->getRepository(BarberProfile::class)->findOneBy(['user' => $barberId]);
        $slotMinutes = $barberSchedule->getSlotMinutes() ?: ($barberProfile ? $barberProfile->getSlotMinutes() : 30);
        $turnDuration = $barberSchedule->getTurnDuration() ?: 60;

        // 3. Get Occupied Slots from Sales
        $sales = $this->entityManager->getRepository(Sale::class)
            ->createQueryBuilder('s')
            ->join('s.details', 'd')
            ->where('d.serviceProvider = :barberId')
            ->andWhere('s.saleDate >= :start')
            ->andWhere('s.saleDate <= :end')
            ->andWhere('s.status != :cancelled')
            ->setParameter('barberId', $barberId)
            ->setParameter('start', $date->format('Y-m-d 00:00:00'))
            ->setParameter('end', $date->format('Y-m-d 23:59:59'))
            ->setParameter('cancelled', SaleStatus::CANCELLED->value)
            ->getQuery()
            ->getResult();

        $occupiedRanges = [];
        foreach ($sales as $sale) {
            $start = $sale->getSaleDate();
            $end = (clone $start)->modify("+{$turnDuration} minutes");
            $occupiedRanges[] = ['start' => $start, 'end' => $end];
        }

        // 4. Get Time Off
        $timeOffs = $this->entityManager->getRepository(BarberTimeOff::class)
            ->createQueryBuilder('to')
            ->where('to.barber = :barberId')
            ->andWhere('to.branch = :branchId OR to.branch IS NULL')
            ->andWhere('to.startAtLocal <= :endOfDay AND to.endAtLocal >= :startOfDay')
            ->setParameter('barberId', $barberId)
            ->setParameter('branchId', $branchId)
            ->setParameter('startOfDay', $date->format('Y-m-d 00:00:00'))
            ->setParameter('endOfDay', $date->format('Y-m-d 23:59:59'))
            ->getQuery()
            ->getResult();

        foreach ($timeOffs as $to) {
            $occupiedRanges[] = ['start' => $to->getStartAtLocal(), 'end' => $to->getEndAtLocal()];
        }

        // 5. Generate Slots
        $slots = [];
        $currentTime = clone $date;
        $currentTime->setTime((int)$barberSchedule->getOpenTime()->format('H'), (int)$barberSchedule->getOpenTime()->format('i'));

        $endTime = clone $date;
        $endTime->setTime((int)$barberSchedule->getCloseTime()->format('H'), (int)$barberSchedule->getCloseTime()->format('i'));

        while ($currentTime < $endTime) {
            $slotStart = clone $currentTime;
            $slotEnd = (clone $currentTime)->modify("+{$turnDuration} minutes");

            if ($slotEnd > $endTime) break;

            $isOccupied = false;

            // 1. Check AppointmentService overlap
            if ($barberProfile && $this->entityManager->getRepository(AppointmentService::class)->hasOverlap($barberProfile->getId(), $slotStart, $turnDuration)) {
                $isOccupied = true;
            }

            if (!$isOccupied) {
                // 2. Check Sales/TimeOff overlaps
                foreach ($occupiedRanges as $range) {
                    $maxStart = max($slotStart->getTimestamp(), $range['start']->getTimestamp());
                    $minEnd = min($slotEnd->getTimestamp(), $range['end']->getTimestamp());

                    if ($maxStart < $minEnd) {
                        $isOccupied = true;
                        break;
                    }
                }
            }

            if (!$isOccupied) {
                // Format: 11:00 AM - 11:30 AM
                $slots[] = $slotStart->format('h:i A') . ' - ' . $slotEnd->format('h:i A');
            }

            $currentTime->modify("+{$slotMinutes} minutes");
        }

        // 6. Group Slots
        $groups = [
            ['id' => 'Mañana', 'icon' => 'Sun', 'times' => []],
            ['id' => 'Tarde', 'icon' => 'CloudSun', 'times' => []],
            ['id' => 'Noche', 'icon' => 'Moon', 'times' => []],
        ];

        foreach ($slots as $timeStr) {
            // Use the start time of the range for grouping (before the " - ")
            $startTimeStr = explode(' - ', $timeStr)[0];
            $time = \DateTime::createFromFormat('h:i A', $startTimeStr);
            $hour = (int)$time->format('H');

            if ($hour < 12) {
                $groups[0]['times'][] = $timeStr;
            } elseif ($hour < 17) {
                $groups[1]['times'][] = $timeStr;
            } else {
                $groups[2]['times'][] = $timeStr;
            }
        }

        // Filter out empty groups
        $result = array_values(array_filter($groups, fn($g) => !empty($g['times'])));

        return $this->json($result, Response::HTTP_OK);
    }

    #[Rest\Get('/barber/public-list')]
    public function publicList(Request $request, UserRepository $userRepository): JsonResponse
    {
        $qb = $userRepository->createQueryBuilder('u')
            ->select('u.id, u.name, u.lastName', 'p.photoUrl', 'p.avgRating', 'p.ratingCount', 'p.classification', 'p.experience')
            ->leftJoin(BarberProfile::class, 'p', 'WITH', 'p.user = u.id')
            ->where('u.barberSn = :isBarber')
            ->andWhere('u.enabled = :enabled')
            ->setParameter('isBarber', true)
            ->setParameter('enabled', true);

        $barbers = $qb->getQuery()->getResult();

        $scheme = $request->getScheme();
        $host = $request->getHttpHost();
        $baseUrl = $scheme . '://' . $host;

        $result = [];
        foreach ($barbers as $barber) {
            $specialties = $this->entityManager->getRepository(BarberSpecialty::class)
                ->createQueryBuilder('bs')
                ->select('s.name')
                ->join('bs.specialty', 's')
                ->where('bs.barber = :barberId')
                ->setParameter('barberId', $barber['id'])
                ->getQuery()
                ->getResult();

            $specialtiesList = array_map(fn($s) => $s['name'], $specialties);

            $result[] = [
                'id' => $barber['id'],
                'name' => $barber['name'] . ($barber['lastName'] ? ' ' . $barber['lastName'] : ''),
                'role' => $barber['classification'] ?? '',
                'experience' => (!empty($barber['experience']) && $barber['experience'] > 0)
                    ? $barber['experience'] . ($barber['experience'] == 1 ? ' año de experiencia' : ' años de experiencia')
                    : null,
                'rating' => (float)$barber['avgRating'],
                'reviewCount' => (int)$barber['ratingCount'],
                'image' => $barber['photoUrl'] ? $baseUrl . $barber['photoUrl'] : null,
                'specialties' => $specialtiesList,
            ];
        }
        return $this->json($result, Response::HTTP_OK);
    }
}
