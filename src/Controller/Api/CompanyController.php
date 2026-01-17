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
            'u.legalName',
            'u.rfc',
            'u.taxAddress',
        ];
    }

    #[Rest\Get('/company')]
    public function index(Request $request, CompanyRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/company')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new Company(), "Empresa creada correctamente");
    }

    #[Rest\Put('/company/{id}')]
    public function update(Request $request, Company $id): JsonResponse
    {
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
        return $this->getDetails($id);
    }
}
