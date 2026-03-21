<?php

namespace App\Controller\Api;

use App\Entity\BarberSchedule;
use App\Entity\BranchHour;
use App\Form\Type\BarberScheduleFormType;
use App\Repository\BarberScheduleRepository;
use App\Repository\BranchHourRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BarberScheduleController extends BaseController
{
    protected function getEntityClass(): string
    {
        return BarberSchedule::class;
    }

    protected function getFormTypeClass(): string
    {
        return BarberScheduleFormType::class;
    }

    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.dayOfWeek',
            'u.openTime',
            'u.closeTime',
            'u.validFrom',
            'u.validTo',
            'u.slotMinutes',
            'u.turnDuration',
            'b.id as branchId',
            'b.name as branchName',
            'barber.id as barberId',
            'barber.name as barberName',
            'barber.lastName as barberLastName'
        ];
    }

    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.branch', 'b');
        $qb->leftJoin('u.barber', 'barber');

        if ($branchId = $request->query->get('branchId')) {
            $qb->andWhere('b.id = :branchId')
                ->setParameter('branchId', $branchId);
        }

        if ($barberId = $request->query->get('barberId')) {
            $qb->andWhere('barber.id = :barberId')
                ->setParameter('barberId', $barberId);
        }
    }

    #[Rest\Get('/barber-schedule')]
    public function index(Request $request, BarberScheduleRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/barber-schedule')]
    public function create(Request $request, BarberScheduleRepository $repository, BranchHourRepository $branchHourRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $validationResult = $this->validateScheduleOverlap($data, $repository, $branchHourRepository);
        if ($validationResult !== null) {
            return $validationResult;
        }

        return $this->processForm($request, new BarberSchedule(), "Horario configurado correctamente");
    }

    #[Rest\Put('/barber-schedule/{id}')]
    public function update(Request $request, BarberSchedule $id, BarberScheduleRepository $repository, BranchHourRepository $branchHourRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $validationResult = $this->validateScheduleOverlap($data, $repository, $branchHourRepository, (int) $id->getId());
        if ($validationResult !== null) {
            return $validationResult;
        }

        return $this->processForm($request, $id, "Horario actualizado correctamente");
    }

    #[Rest\Post('/barber-schedule/generate-weekly')]
    public function generateWeekly(Request $request, BarberScheduleRepository $repository, BranchHourRepository $branchHourRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $barberId = $data['barberId'] ?? null;
        $branchId = $data['branchId'] ?? null;
        $openTimeStr = $data['openTime'] ?? null;
        $closeTimeStr = $data['closeTime'] ?? null;
        $turnDurationStr = $data['turnDuration'] ?? 30;
        $slotMinutesStr = $data['slotMinutes'] ?? 30;
        $ignoreConflicts = $data['ignoreConflicts'] ?? false;

        if (!$barberId || !$branchId || !$openTimeStr || !$closeTimeStr) {
            return $this->json(['message' => 'Faltan parámetros requeridos.'], Response::HTTP_BAD_REQUEST);
        }

        $barber = $this->entityManager->getRepository(\App\Entity\User::class)->find($barberId);
        $branch = $this->entityManager->getRepository(\App\Entity\Branch::class)->find($branchId);

        if (!$barber || !$branch) {
            return $this->json(['message' => 'Barbero o sucursal no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $openTime = new \DateTime('1970-01-01 ' . $openTimeStr);
        $closeTime = new \DateTime('1970-01-01 ' . $closeTimeStr);
        $validFrom = new \DateTime();

        try {
            $branchHours = $branchHourRepository->findBy(['branch' => $branch]);
            $errores = [];
            $validDays = [];
            $dayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

            // Verify all 7 days first
            for ($day = 1; $day <= 7; $day++) {
                $bh = null;
                foreach ($branchHours as $bhour) {
                    if ($bhour->getDayOfWeek() == $day && $bhour->getDeletedAt() === null) {
                        $bh = $bhour; break;
                    }
                }

                if (!$bh) {
                    $errores[] = "La sucursal no está configurada o abierta el " . $dayNames[$day] . " (Configura su horario de sucursal primero)";
                    continue;
                }

                $branchOpen = new \DateTime('1970-01-01 ' . $bh->getOpenTime()->format('H:i:s'));
                $branchClose = new \DateTime('1970-01-01 ' . $bh->getCloseTime()->format('H:i:s'));

                if ($openTime < $branchOpen || $closeTime > $branchClose) {
                    $errores[] = "El horario deseado el " . $dayNames[$day] . " excede el horario de la sucursal (" . $branchOpen->format('H:i') . " - " . $branchClose->format('H:i') . ")";
                    continue;
                }

                $overlapping = $repository->findOverlappingSchedules(
                    (int) $barberId,
                    $day,
                    $openTime,
                    $closeTime,
                    $validFrom,
                    null,
                    null
                );

                $hasOverlap = false;
                foreach ($overlapping as $overlap) {
                    // Ignore overlaps within the same branch as they will be wiped.
                    if ($overlap->getBranch()->getId() !== $branch->getId()) {
                        $errores[] = "El barbero ya tiene horario asignado en " . $overlap->getBranch()->getName() . " el " . $dayNames[$day];
                        $hasOverlap = true;
                        break;
                    }
                }
                if ($hasOverlap) {
                    continue;
                }

                $validDays[] = $day;
            }

            if (!empty($errores) && !$ignoreConflicts) {
                return $this->json([
                    'message' => 'Conflictos detectados',
                    'conflicts' => $errores
                ], Response::HTTP_CONFLICT);
            }

            if (empty($validDays)) {
                return $this->json([
                    'message' => "No hay días válidos para generar horarios. Por favor revisa los conflictos reportados."
                ], Response::HTTP_BAD_REQUEST);
            }

            $existingSchedules = $repository->findBy([
                'barber' => $barber,
                'branch' => $branch
            ]);
            
            foreach ($existingSchedules as $schedule) {
                $this->entityManager->remove($schedule);
            }
            
            foreach ($validDays as $day) {
                $schedule = new BarberSchedule();
                $schedule->setBarber($barber);
                $schedule->setBranch($branch);
                $schedule->setDayOfWeek($day);
                $schedule->setOpenTime($openTime);
                $schedule->setCloseTime($closeTime);
                $schedule->setSlotMinutes((int)$slotMinutesStr);
                $schedule->setTurnDuration((int)$turnDurationStr);
                $schedule->setValidFrom($validFrom);
                $schedule->setValidTo(null);

                $this->entityManager->persist($schedule);
            }

            $this->entityManager->flush();

            return $this->json([
                'message' => empty($errores) 
                    ? 'Horarios generados correctamente para los 7 días.' 
                    : 'Horarios generados omitiendo los días conflictivos.'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Error al generar horarios: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateScheduleOverlap(array $data, BarberScheduleRepository $repository, BranchHourRepository $branchHourRepository, ?int $currentId = null): ?JsonResponse
    {
        $barberId = $data['barber'] ?? null;
        $branchId = $data['branch'] ?? null;
        $dayOfWeek = $data['dayOfWeek'] ?? null;
        $openTimeStr = $data['openTime'] ?? null;
        $closeTimeStr = $data['closeTime'] ?? null;
        $validFromStr = $data['validFrom'] ?? null;
        $validToStr = $data['validTo'] ?? null;

        if (!$barberId || !$branchId || $dayOfWeek === null || !$openTimeStr || !$closeTimeStr || !$validFromStr) {
            return null;
        }

        $openTime = new \DateTime('1970-01-01 ' . $openTimeStr);
        $closeTime = new \DateTime('1970-01-01 ' . $closeTimeStr);
        $validFrom = new \DateTime($validFromStr);
        $validTo = $validToStr ? new \DateTime($validToStr) : null;

        // 1. Validate against Branch Hours
        $branchHour = $branchHourRepository->findOneBy([
            'branch' => $branchId,
            'dayOfWeek' => $dayOfWeek
        ]);

        if (!$branchHour) {
            return $this->json([
                'message' => 'La sucursal no tiene horario configurado para este día.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $branchOpen = new \DateTime('1970-01-01 ' . $branchHour->getOpenTime()->format('H:i:s'));
        $branchClose = new \DateTime('1970-01-01 ' . $branchHour->getCloseTime()->format('H:i:s'));

        if ($openTime < $branchOpen || $closeTime > $branchClose) {
            return $this->json([
                'message' => sprintf(
                    'El horario del barbero debe estar dentro del horario de la sucursal (%s - %s).',
                    $branchOpen->format('H:i'),
                    $branchClose->format('H:i')
                )
            ], Response::HTTP_BAD_REQUEST);
        }

        // 2. Validate against overlapping barber shifts (in any branch)
        $overlapping = $repository->findOverlappingSchedules(
            (int) $barberId,
            (int) $dayOfWeek,
            $openTime,
            $closeTime,
            $validFrom,
            $validTo,
            $currentId
        );

        if (!empty($overlapping)) {
            $overlap = $overlapping[0];
            $branchName = $overlap->getBranch()->getName();
            return $this->json([
                'message' => sprintf(
                    'Solape detectado en %s el día %s: Ya existe un turno de %s a %s en el rango %s a %s.',
                    $branchName,
                    [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'][$overlap->getDayOfWeek()],
                    $overlap->getOpenTime()->format('H:i'),
                    $overlap->getCloseTime()->format('H:i'),
                    $overlap->getValidFrom()->format('d/m/Y'),
                    $overlap->getValidTo() ? $overlap->getValidTo()->format('d/m/Y') : 'Indefinido'
                )
            ], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    #[Rest\Delete('/barber-schedule/{id}')]
    public function remove(BarberSchedule $id): JsonResponse
    {
        try {
            $this->entityManager->remove($id);
            $this->entityManager->flush();
            return $this->json(['message' => 'Horario eliminado físicamente'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Error al eliminar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Rest\Get('/barber-schedule/{id}')]
    public function get(BarberSchedule $id): mixed
    {
        return $this->getDetails($id);
    }
}
