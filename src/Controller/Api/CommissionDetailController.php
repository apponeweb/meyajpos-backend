<?php

namespace App\Controller\Api;

use App\Entity\Commission;
use App\Entity\CommissionDetail;
use App\Form\Type\CommissionDetailFormType;
use App\Repository\CommissionDetailsRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CommissionDetailController extends BaseController
{
    private $currentCommissionId = null;

    protected function getEntityClass(): string
    {
        return CommissionDetail::class;
    }

    protected function getFormTypeClass(): string
    {
        return CommissionDetailFormType::class;
    }

    /**
     * Sobrescribimos los campos a seleccionar para el listado paginado
     */
    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.percentage',
            'u.applicableCommission',
            'st.name as serviceTypeName',
            'c.id as commissionId',
        ];
    }

    /**
     * HOOK: Aplicamos el filtro por comisión antes de ejecutar el listado
     */
    protected function configureListQuery(\Doctrine\ORM\QueryBuilder $qb, Request $request): void
    {
        // Obtenemos el ID de la comisión desde los atributos de la ruta
        $commissionId = $request->attributes->get('commissionId');

        $qb->join('u.serviceType', 'st');
        $qb->join('u.commission', 'c');

        $qb->andWhere('u.commission = :commissionId')->setParameter('commissionId', $commissionId);
    }

    /**
     * Listar los detalles de una comisión específica
     */
    #[Rest\Get('/commission/{commissionId}/details')]
    public function indexDetails(Request $request, CommissionDetailsRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    /**
     * Adicionar un detalle a una comisión
     */
    #[Rest\Post('/commission/{id}/details')]
    public function addDetail(Request $request, Commission $commission): JsonResponse
    {
        $detail = new CommissionDetail();
        $detail->setCommission($commission); // Vinculamos con el padre

        return $this->processForm($request, $detail, "Detalle de comisión agregado");
    }

    /**
     * Actualizar un detalle
     */
    #[Rest\Put('/commission/{id}/detail')]
    public function updateDetail(Request $request, CommissionDetail $detail): JsonResponse
    {
        return $this->processForm($request, $detail, "Detalle actualizado");
    }

    /**
     * Eliminar un detalle (Usa el método delete del BaseController)
     */
    #[Rest\Delete('/commission/{id}/detail')]
    public function removeDetail(CommissionDetail $detail): JsonResponse
    {
        return $this->delete($detail);
    }
}
