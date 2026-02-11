<?php

namespace App\Controller\Api;

use App\Entity\Commission;
use App\Entity\Sale;
use App\Form\Type\CommissionFormType;
use App\Repository\CommissionRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CommissionController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Commission::class;
    }

    protected function getFormTypeClass(): string
    {
        return CommissionFormType::class;
    }


    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.name',
            'u.description',
            'u.isActive'
        ];
    }

    #[Rest\Get('/commission')]
    public function index(Request $request, CommissionRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Get('/commission/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(CommissionRepository $commissionRepository)
    {
        return $commissionRepository->getAllToSelect();
    }

    #[Rest\Post('/commission')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new Commission(), "Comisión creada correctamente");
    }

    #[Rest\Put('/commission/{id}')]
    public function update(Request $request, Commission $id): JsonResponse
    {
        return $this->processForm($request, $id, "Comisión actualizada correctamente");
    }

    #[Rest\Delete('/commission/{id}')]
    public function remove(Commission $id): mixed
    {
        $sales = $this->entityManager->getRepository(Sale::class)->count(['cashBox' => $id]);
        if ($sales > 0) {
            return $this->json([
                'message' => "No se puede eliminar la caja porque tiene ventas asociadas",
            ], Response::HTTP_BAD_REQUEST);
        }
        return $this->delete($id);
    }

    #[Rest\Get('/commission/{id}')]
    public function get(Commission $id): mixed
    {
        // 1. Llamamos al método base para obtener la respuesta original
        $response = $this->getDetails($id);

        // 2. Si la respuesta no es un éxito (ej. 404 o 500), la retornamos tal cual
        if ($response->getStatusCode() !== JsonResponse::HTTP_OK) {
            return $response;
        }

        // 3. Decodificamos el contenido para manipularlo
        $data = json_decode($response->getContent(), true);

        // 4. Ajuste puntual: renombramos 'active' a 'isActive' si existe
        if (isset($data['active']) && !isset($data['isActive'])) {
            $data['isActive'] = $data['active'];
            unset($data['active']);
        }

        // 6. Retornamos la respuesta ya corregida
        return new JsonResponse($data, $response->getStatusCode());
    }
}
