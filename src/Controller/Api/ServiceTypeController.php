<?php

namespace App\Controller\Api;

use App\Entity\CommissionDetail;
use App\Entity\Sale;
use App\Entity\ServiceType;
use App\Form\Type\ServiceTypeFormType;
use App\Repository\ServiceTypeRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ServiceTypeController extends BaseController
{
    protected function getEntityClass(): string
    {
        return ServiceType::class;
    }

    protected function getFormTypeClass(): string
    {
        return ServiceTypeFormType::class;
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

    #[Rest\Get('/service_type')]
    public function index(Request $request, ServiceTypeRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Get('/service_type/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(ServiceTypeRepository $serviceTypeRepository)
    {
        return $serviceTypeRepository->getAllToSelect();
    }

    #[Rest\Post('/service_type')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new ServiceType(), "Tipo de servicio creado correctamente");
    }

    #[Rest\Put('/service_type/{id}')]
    public function update(Request $request, ServiceType $id): JsonResponse
    {
        return $this->processForm($request, $id, "Tipo de servicio actualizado correctamente");
    }

    #[Rest\Delete('/service_type/{id}')]
    public function remove(ServiceType $id): mixed
    {
        $sales = $this->entityManager->getRepository(CommissionDetail::class)->count(['serviceType' => $id]);
        if ($sales > 0) {
            return $this->json([
                'message' => "No se puede eliminar el tipo de servicio porque tiene asociado comisiones",
            ], Response::HTTP_BAD_REQUEST);
        }
        return $this->delete($id);
    }

    #[Rest\Get('/service_type/{id}')]
    public function get(ServiceType $id): mixed
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


        // 5. Aprovechamos para formatear el precio como en el método list
        if (isset($data['price'])) {
            $data['price'] = number_format((float)$data['price'], 2, '.', ',');
        }

        // 6. Retornamos la respuesta ya corregida
        return new JsonResponse($data, $response->getStatusCode());
    }
}
