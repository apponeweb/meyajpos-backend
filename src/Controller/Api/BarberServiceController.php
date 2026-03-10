<?php

namespace App\Controller\Api;

use App\Entity\BarberService;
use App\Form\Type\BarberServiceFormType;
use App\Repository\BarberServiceRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BarberServiceController extends BaseController
{
    protected function getEntityClass(): string
    {
        return BarberService::class;
    }

    protected function getFormTypeClass(): string
    {
        return BarberServiceFormType::class;
    }

    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.durationOverrideMinutes',
            'u.isActive',
            'product.id as productId',
            'product.name as productName',
            'product.description as productDescription',
            'product.price as productPrice',
            'barber.id as barberId'
        ];
    }

    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.product', 'product');
        $qb->leftJoin('u.barber', 'barber');

        if ($barberId = $request->query->get('barberId')) {
            $qb->andWhere('barber.id = :barberId')
                ->setParameter('barberId', $barberId);
        }
    }

    #[Rest\Get('/barber-services')]
    public function index(Request $request, BarberServiceRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/barber-services')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new BarberService(), "Servicio asociado correctamente");
    }

    #[Rest\Put('/barber-services/{id}')]
    public function update(Request $request, BarberService $id): JsonResponse
    {
        return $this->processForm($request, $id, "Asociación actualizada correctamente");
    }

    #[Rest\Delete('/barber-services/{id}')]
    public function remove(BarberService $id): JsonResponse
    {
        try {
            $this->entityManager->remove($id);
            $this->entityManager->flush();
            return $this->json(['message' => 'Asociación eliminada'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Error al eliminar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
