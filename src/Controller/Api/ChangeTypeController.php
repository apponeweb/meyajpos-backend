<?php

namespace App\Controller\Api;

use App\Entity\ChangeType;
use App\Form\Type\ChangeTypeFormType;
use App\Repository\ChangeTypeRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ChangeTypeController extends BaseController
{
    protected function getEntityClass(): string
    {
        return ChangeType::class;
    }

    protected function getFormTypeClass(): string
    {
        return ChangeTypeFormType::class;
    }

    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.currencyOrigin', 'co');
        $qb->leftJoin('u.currencyDestination', 'cd');

        if ($currencyOriginId = $request->query->get('currencyOriginId')) {
            $qb->andWhere('co.id = :currencyOriginId')
                ->setParameter('currencyOriginId', $currencyOriginId);
        }

        if ($currencyDestinationId = $request->query->get('currencyDestinationId')) {
            $qb->andWhere('cd.id = :currencyDestinationId')
                ->setParameter('currencyDestinationId', $currencyDestinationId);
        }
    }

    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.name',
            'u.description',
            'u.isActive',
            'co.name as currencyOriginName',
            'cd.name as currencyDestinationName',
            'u.changeType',
            'u.taxDate',
            'u.source'
        ];
    }

    #[Rest\Get('/change_type')]
    public function index(Request $request, ChangeTypeRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/change_type')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new ChangeType(), "Tipo de cambio creado correctamente");
    }

    #[Rest\Put('/change_type/{id}')]
    public function update(Request $request, ChangeType $id): JsonResponse
    {
        return $this->processForm($request, $id, "Tipo de cambio actualizado correctamente");
    }

    #[Rest\Delete('/change_type/{id}')]
    public function remove(ChangeType $id): mixed
    {
        return $this->delete($id);
    }
}
