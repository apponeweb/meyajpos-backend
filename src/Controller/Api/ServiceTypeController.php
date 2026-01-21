<?php

namespace App\Controller\Api;

use App\Entity\ServiceType;
use App\Form\Type\ServiceTypeFormType;
use App\Repository\ServiceTypeRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
        return $this->delete($id);
    }

    #[Rest\Get('/service_type/{id}')]
    public function get(ServiceType $id): mixed
    {
        return $this->getDetails($id);
    }
}
