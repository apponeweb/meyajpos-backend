<?php

namespace App\Controller\Api;

use App\Entity\CashBox;
use App\Form\Type\CashBoxFormType;
use App\Repository\CashBoxRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CashBoxController extends BaseController
{
    protected function getEntityClass(): string
    {
        return CashBox::class;
    }

    protected function getFormTypeClass(): string
    {
        return CashBoxFormType::class;
    }


    // Sobrescribimos para añadir el Join y un filtro extra
    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.branch', 'c'); // 'u' siempre es la entidad principal
        $qb->leftJoin('c.company', 'o');

        // Ejemplo: Filtro opcional por compañía si viene en la URL (?branch=1)
        if ($branchId = $request->query->get('branchId')) {
            $qb->andWhere('c.id = :branchId')
                ->setParameter('branchId', $branchId);
        }
        if ($companyId = $request->query->get('companyId')) {
            $qb->andWhere('o.id = companyId')
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
            'c.name as branchName', // Atributo adicional de la relación
            'c.id as branchId',
            'o.name as companyName', // Atributo adicional de la relación
            'o.id as companyId',
            'u.currentFolio',
            'u.ticketSerie'
        ];
    }

    #[Rest\Get('/cash_box')]
    public function index(Request $request, CashBoxRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Get('/cash_box/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(CashBoxRepository $cashBoxRepository)
    {
        return $cashBoxRepository->getAllToSelect();
    }

    #[Rest\Post('/cash_box')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new CashBox(), "Caja creada correctamente");
    }

    #[Rest\Put('/cash_box/{id}')]
    public function update(Request $request, CashBox $id): JsonResponse
    {
        return $this->processForm($request, $id, "Caja actualizada correctamente");
    }

    #[Rest\Delete('/cash_box/{id}')]
    public function remove(CashBox $id): mixed
    {
        return $this->delete($id);
    }

    #[Rest\Get('/cash_box/{id}')]
    public function get(CashBox $id): mixed
    {
        return $this->getDetails($id);
    }


}
