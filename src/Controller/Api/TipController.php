<?php

namespace App\Controller\Api;

use App\Entity\Tip;
use App\Entity\User;
use App\Form\Type\TipFormType;
use App\Repository\TipRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class TipController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Tip::class;
    }

    protected function getFormTypeClass(): string
    {
        return TipFormType::class;
    }


    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.amount',
            "DateFormat(u.tipDate, '%d/%m/%Y %H:%i:%s') as tipDate",
            'u.isActive',
            'pt.id'
        ];
    }

    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->join('u.salePayment', 'sp'); // 'u' siempre es la entidad principal
        $qb->join('u.user', 'user'); // 'u' siempre es la entidad principal
        $qb->join('u.paymentType', 'pt'); // 'u' siempre es la entidad principal

        // Ejemplo: Filtro opcional por compañía si viene en la URL (?companyId=1)
        if ($companyId = $request->query->get('companyId')) {
            $qb->andWhere('c.id = :companyId')
                ->setParameter('companyId', $companyId);
        }
    }

    #[Rest\Get('/tip')]
    public function index(Request $request, TipRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/tip')]
    public function create(Request $request): JsonResponse
    {
        $tip = new Tip();

        // Asignamos el usuario autenticado manualmente
        $currentUser = $this->security->getUser();
        if ($currentUser instanceof User) {
            $tip->setUser($currentUser);
        }

        return $this->processForm($request, $tip, "Propina registrada con éxito");
    }

    #[Rest\Put('/tip/{id}')]
    public function update(Request $request, Tip $id): JsonResponse
    {
        return $this->processForm($request, $id, "Propina actualizada correctamente");
    }

    #[Rest\Delete('/tip/{id}')]
    public function remove(Tip $id): mixed
    {
        return $this->delete($id);
    }

    #[Rest\Get('/tip/{id}')]
    public function get(Tip $id): mixed
    {
        return $this->getDetails($id);
    }
}
