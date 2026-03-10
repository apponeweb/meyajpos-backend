<?php

namespace App\Controller\Api;

use App\Entity\BarberSpecialty;
use App\Form\Type\BarberSpecialtyFormType;
use App\Repository\BarberSpecialtyRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class BarberSpecialtyController extends BaseController
{
    protected function getEntityClass(): string
    {
        return BarberSpecialty::class;
    }

    protected function getFormTypeClass(): string
    {
        return BarberSpecialtyFormType::class;
    }

    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'b.id as barberId',
            's.id as specialtyId',
            's.name as specialtyName',
            'u.isActive'
        ];
    }

    protected function configureListQuery($qb, Request $request): void
    {
        $qb->join('u.barber', 'b')
            ->join('u.specialty', 's');

        if ($barberId = $request->query->get('barberId')) {
            $qb->andWhere('b.id = :barberId')
                ->setParameter('barberId', $barberId);
        }
    }

    #[Rest\Get('/barber-specialty')]
    public function index(Request $request, BarberSpecialtyRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/barber-specialty')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new BarberSpecialty(), "Especialidad asignada correctamente");
    }

    #[Rest\Put('/barber-specialty/{id}')]
    public function update(Request $request, BarberSpecialty $id): JsonResponse
    {
        return $this->processForm($request, $id, "Asignación actualizada correctamente");
    }

    #[Rest\Delete('/barber-specialty/{id}')]
    public function remove(BarberSpecialty $id): JsonResponse
    {
        return $this->delete($id);
    }
}
