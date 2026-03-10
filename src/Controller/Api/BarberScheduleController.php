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
