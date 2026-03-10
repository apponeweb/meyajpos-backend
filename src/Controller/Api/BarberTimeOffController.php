<?php

namespace App\Controller\Api;

use App\Entity\BarberTimeOff;
use App\Form\Type\BarberTimeOffFormType;
use App\Repository\BarberTimeOffRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BarberTimeOffController extends BaseController
{
    protected function getEntityClass(): string
    {
        return BarberTimeOff::class;
    }

    protected function getFormTypeClass(): string
    {
        return BarberTimeOffFormType::class;
    }

    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.startAtLocal',
            'u.endAtLocal',
            'u.reason',
            'u.createdAt',
            'barber.id as barberId',
            'barber.name as barberName',
            'barber.lastName as barberLastName',
            'branch.id as branchId',
            'branch.name as branchName'
        ];
    }

    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.barber', 'barber');
        $qb->leftJoin('u.branch', 'branch');

        if ($barberId = $request->query->get('barberId')) {
            $qb->andWhere('barber.id = :barberId')
                ->setParameter('barberId', $barberId);
        }
    }

    #[Rest\Get('/barber-time-off')]
    public function index(Request $request, BarberTimeOffRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/barber-time-off')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new BarberTimeOff(), "Afectación registrada correctamente");
    }

    #[Rest\Put('/barber-time-off/{id}')]
    public function update(Request $request, BarberTimeOff $id): JsonResponse
    {
        return $this->processForm($request, $id, "Afectación actualizada correctamente");
    }

    #[Rest\Delete('/barber-time-off/{id}')]
    public function remove(BarberTimeOff $id): JsonResponse
    {
        try {
            $this->entityManager->remove($id);
            $this->entityManager->flush();
            return $this->json(['message' => 'Afectación eliminada físicamente'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Error al eliminar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Rest\Get('/barber-time-off/{id}')]
    public function get(BarberTimeOff $id): mixed
    {
        return $this->getDetails($id);
    }
}
