<?php

namespace App\Controller\Api;

use App\Entity\MasterProduct;
use App\Form\Type\MasterProductFormType;
use App\Repository\MasterProductRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class MasterProductController extends BaseController
{
    protected function getEntityClass(): string
    {
        return MasterProduct::class;
    }

    protected function getFormTypeClass(): string
    {
        return MasterProductFormType::class;
    }

    /**
     * Configuramos el QueryBuilder para incluir relaciones y filtros de búsqueda
     */
    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        // 'u' es el alias de MasterProduct definido en tu BaseController
        $qb->leftJoin('u.serviceType', 'st');

        // Filtro opcional por SKU
        if ($sku = $request->query->get('sku')) {
            $qb->andWhere('u.sku LIKE :sku')
                ->setParameter('sku', '%' . $sku . '%');
        }

        // Filtro opcional por tipo de servicio (?serviceTypeId=1)
        if ($serviceTypeId = $request->query->get('serviceTypeId')) {
            $qb->andWhere('st.id = :serviceTypeId')
                ->setParameter('serviceTypeId', $serviceTypeId);
        }

        // Filtro por si es inventariable o no (?isInventoriable=true)
        if ($request->query->has('isInventoriable')) {
            $value = $request->query->getBoolean('isInventoriable');
            $qb->andWhere('u.isInventoriable = :isInvent')
                ->setParameter('isInvent', $value);
        }
    }

    /**
     * Definimos los campos que se retornarán en la lista JSON
     */
    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.name',
            'u.description',
            'u.isActive',
            'u.sku',
            'u.barcode',
            'u.price',
            'u.uom',
            'u.isInventoriable',
            'st.id as serviceTypeId',
            'st.name as serviceTypeName'
        ];
    }

    #[Rest\Get('/master_product')]
    public function index(Request $request, MasterProductRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Get('/master_product/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(MasterProductRepository $masterProductRepository)
    {
        $extraColumns = ['price'];
        return $masterProductRepository->getAllToSelect($extraColumns);
    }

    #[Rest\Post('/master_product')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new MasterProduct(), "Producto maestro creado correctamente");
    }

    #[Rest\Put('/master_product/{id}')]
    public function update(Request $request, MasterProduct $id): JsonResponse
    {
        return $this->processForm($request, $id, "Producto maestro actualizado correctamente");
    }

    #[Rest\Delete('/master_product/{id}')]
    public function remove(MasterProduct $id): JsonResponse
    {
        return $this->delete($id);
    }

    #[Rest\Get('/master_product/{id}')]
    public function get(MasterProduct $id): JsonResponse
    {
        return $this->getDetails($id);
    }

    #[Rest\Get('/master_product/barcode/{barcode}')]
    public function getByBarcode(string $barcode, MasterProductRepository $repository): JsonResponse
    {
        $product = $repository->findDetailsByBarcode($barcode);

        if (!$product) {
            return $this->json([
                'message' => 'Producto no encontrado con el código de barras proporcionado',
                'data' => null
            ], JsonResponse::HTTP_NOT_FOUND);
        }
        return $this->json([
            'message' => 'Producto recuperado con éxito',
            'data' => $product
        ], JsonResponse::HTTP_OK);
    }
}
