<?php

namespace App\Controller\Api;

use App\Entity\Sale;
use App\Form\Type\SaleFormType;
use App\Repository\SaleRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class SaleController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Sale::class;
    }

    protected function getFormTypeClass(): string
    {
        return SaleFormType::class;
    }

    /**
     * Configuramos los joins para obtener información de la Caja y el Usuario
     */
    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        // 'u' es la entidad Sale
        $qb->leftJoin('u.cashBox', 'cb')
            ->leftJoin('u.user', 'usr');

        // Filtro por Folio
        if ($folio = $request->query->get('folio')) {
            $qb->andWhere('u.folio LIKE :folio')
                ->setParameter('folio', '%' . $folio . '%');
        }

        // Filtro por Estado (Enum value)
        if ($status = $request->query->get('status')) {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', $status);
        }

        // Filtro por Rango de Fechas
        if ($startDate = $request->query->get('startDate')) {
            $qb->andWhere('u.dateTime >= :start')
                ->setParameter('start', new \DateTime($startDate));
        }
        if ($endDate = $request->query->get('endDate')) {
            $qb->andWhere('u.dateTime <= :end')
                ->setParameter('end', new \DateTime($endDate . ' 23:59:59'));
        }
    }

    /**
     * Definimos los campos de la venta, incluyendo el total y datos de relaciones
     */
    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.folio',
            'u.saleDate',
            'u.subtotal',
            'u.totalTax',
            'u.total',
            'u.status', // Doctrine serializará el valor prestablecido del Enum
            'cb.id as cashBoxId',
            'cb.name as cashBoxName',
            'usr.id as userId',
            'usr.name as userName'
        ];
    }

    #[Rest\Get('/sale')]
    public function index(Request $request, SaleRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/sale')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new Sale(), "Venta registrada correctamente");
    }

    #[Rest\Put('/sale/{id}')]
    public function update(Request $request, Sale $id): JsonResponse
    {
        // Útil para cancelaciones o cambios de estado
        return $this->processForm($request, $id, "Venta actualizada correctamente");
    }

    #[Rest\Delete('/sale/{id}')]
    public function remove(Sale $id): JsonResponse
    {
        return $this->delete($id);
    }

    #[Rest\Get('/sale/{id}')]
    public function get(Sale $id): JsonResponse
    {
        return $this->getDetails($id);
    }
}
