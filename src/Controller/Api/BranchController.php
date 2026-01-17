<?php

namespace App\Controller\Api;

use App\Entity\Branch;
use App\Form\Type\BranchFormType;
use App\Repository\BranchRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class BranchController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Branch::class;
    }

    protected function getFormTypeClass(): string
    {
        return BranchFormType::class;
    }


    // Sobrescribimos para añadir el Join y un filtro extra
    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.company', 'c'); // 'u' siempre es la entidad principal

        // Ejemplo: Filtro opcional por compañía si viene en la URL (?companyId=1)
        if ($companyId = $request->query->get('companyId')) {
            $qb->andWhere('c.id = :companyId')
                ->setParameter('companyId', $companyId);
        }
    }

    // Sobrescribimos para añadir campos de la relación al JSON
    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.name',
            'u.description',
            'u.isActive',
            'u.acronym',
            'u.address',
            'c.name as companyName',
            'c.id as companyId'
        ];
    }

    #[Rest\Get('/branch')]
    public function index(Request $request, BranchRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/branch')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new Branch(), "Sucursal creada correctamente");
    }

    #[Rest\Put('/branch/{id}')]
    public function update(Request $request, Branch $id): JsonResponse
    {
        return $this->processForm($request, $id, "Sucursal actualizada correctamente");
    }

    #[Rest\Delete('/branch/{id}')]
    public function remove(Branch $id): mixed
    {
        return $this->delete($id);
    }

    #[Rest\Get('/branch/{id}')]
    public function get(Branch $id): mixed
    {
        return $this->getDetails($id);
    }
}
