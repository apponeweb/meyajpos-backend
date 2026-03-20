<?php

namespace App\Controller\Api;

use App\Entity\Branch;
use App\Entity\BranchHour;
use App\Form\Type\BranchHourFormType;
use App\Repository\BranchHourRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BranchHourController extends BaseController
{
    protected function getEntityClass(): string
    {
        return BranchHour::class;
    }

    protected function getFormTypeClass(): string
    {
        return BranchHourFormType::class;
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
            'b.id as branchId',
            'b.name as branchName'
        ];
    }

    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.branch', 'b');

        if ($branchId = $request->query->get('branchId')) {
            $qb->andWhere('b.id = :branchId')
                ->setParameter('branchId', $branchId);
        }

        if ($dayOfWeek = $request->query->get('dayOfWeek')) {
            $qb->andWhere('u.dayOfWeek = :dayOfWeek')
                ->setParameter('dayOfWeek', $dayOfWeek);
        }
    }

    #[Rest\Get('/branch-hour')]
    public function index(Request $request, BranchHourRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/branch-hour')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if ($this->checkDuplicateDay($data)) {
            return $this->json(['message' => 'Ya existe un horario configurado para este día en la sucursal.'], Response::HTTP_BAD_REQUEST);
        }
        return $this->processForm($request, new BranchHour(), "Horario configurado correctamente");
    }

    #[Rest\Put('/branch-hour/{id}')]
    public function update(Request $request, BranchHour $id): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if ($this->checkDuplicateDay($data, (int) $id->getId())) {
            return $this->json(['message' => 'Ya existe un horario configurado para este día en la sucursal.'], Response::HTTP_BAD_REQUEST);
        }
        return $this->processForm($request, $id, "Horario actualizado correctamente");
    }

    #[Rest\Post('/branch-hour/generate-weekly')]
    public function generateWeekly(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $branchId = $data['branchId'] ?? null;
        $openTimeStr = $data['openTime'] ?? null;
        $closeTimeStr = $data['closeTime'] ?? null;

        if (!$branchId || !$openTimeStr || !$closeTimeStr) {
            return $this->json(['message' => 'Faltan parámetros requeridos.'], Response::HTTP_BAD_REQUEST);
        }

        $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
        if (!$branch) {
            return $this->json(['message' => 'Sucursal no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $repository = $this->entityManager->getRepository(BranchHour::class);
            $existingHours = $repository->findBy(['branch' => $branch]);
            
            foreach ($existingHours as $hour) {
                $this->entityManager->remove($hour);
            }

            $openTime = new \DateTime($openTimeStr);
            $closeTime = new \DateTime($closeTimeStr);
            $validFrom = new \DateTime();

            for ($day = 1; $day <= 7; $day++) {
                $branchHour = new BranchHour();
                $branchHour->setBranch($branch);
                $branchHour->setDayOfWeek($day);
                $branchHour->setOpenTime($openTime);
                $branchHour->setCloseTime($closeTime);
                $branchHour->setValidFrom($validFrom);
                $branchHour->setValidTo(null);

                $this->entityManager->persist($branchHour);
            }

            $this->entityManager->flush();

            return $this->json(['message' => 'Horarios generados correctamente.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Error al generar horarios: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function checkDuplicateDay(array $data, ?int $currentId = null): bool
    {
        $branchId = $data['branch'] ?? null;
        $dayOfWeek = $data['dayOfWeek'] ?? null;

        if (!$branchId || !$dayOfWeek)
            return false;

        $repository = $this->entityManager->getRepository(BranchHour::class);
        $qb = $repository->createQueryBuilder('u')
            ->where('u.branch = :branchId')
            ->andWhere('u.dayOfWeek = :dayOfWeek')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('branchId', $branchId)
            ->setParameter('dayOfWeek', $dayOfWeek);

        if ($currentId) {
            $qb->andWhere('u.id != :currentId')
                ->setParameter('currentId', $currentId);
        }

        return count($qb->getQuery()->getResult()) > 0;
    }

    #[Rest\Delete('/branch-hour/{id}')]
    public function remove(BranchHour $id): JsonResponse
    {
        try {
            $this->entityManager->remove($id);
            $this->entityManager->flush();
            return $this->json(['message' => 'Horario eliminado físicamente'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Error al eliminar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Rest\Get('/branch-hour/{id}')]
    public function get(BranchHour $id): mixed
    {
        return $this->getDetails($id);
    }
}
