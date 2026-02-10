<?php

namespace App\Controller\Api;

use App\Entity\Company;
use App\Form\Type\CompanyFormType;
use App\Repository\CompanyRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CompanyController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Company::class;
    }

    protected function getFormTypeClass(): string
    {
        return CompanyFormType::class;
    }

    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.name',
            'u.description',
            'u.isActive',
            'u.acronym',
            'u.phone',
            'u.legalName',
            'u.rfc',
            'u.taxAddress',
        ];
    }

    #[Rest\Get('/company/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(CompanyRepository $companyRepository)
    {
        return $companyRepository->getAllToSelect();
    }

    #[Rest\Get('/company')]
    public function index(Request $request, CompanyRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/company')]
    public function create(Request $request): JsonResponse
    {
        $this->normalizeAddress($request, 'taxAddress');
        return $this->processForm($request, new Company(), "Empresa creada correctamente");
    }

    #[Rest\Put('/company/{id}')]
    public function update(Request $request, Company $id): JsonResponse
    {
        $this->normalizeAddress($request, 'taxAddress');
        return $this->processForm($request, $id, "Empresa actualizada correctamente");
    }

    #[Rest\Delete('/company/{id}')]
    public function remove(Company $id): mixed
    {
        return $this->delete($id);
    }

    #[Rest\Get('/company/{id}')]
    public function get(Company $id): mixed
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
