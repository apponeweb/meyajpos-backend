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
        $this->normalizeTaxAddress($request);
        return $this->processForm($request, new Company(), "Empresa creada correctamente");
    }

    #[Rest\Put('/company/{id}')]
    public function update(Request $request, Company $id): JsonResponse
    {
        $this->normalizeTaxAddress($request);
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


    #[Rest\Get('/company/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(CompanyRepository $companyRepository)
    {
        return $companyRepository->getAllToSelect();
    }
    /**
     * Normaliza el campo taxAddress transformando el objeto JSON en un string
     * para que sea compatible con el campo TEXT de la entidad.
     */
    private function normalizeTaxAddress(Request $request): void
    {
        $content = json_decode($request->getContent(), true);

        if (isset($content['taxAddress']) && is_array($content['taxAddress'])) {
            $content['taxAddress'] = json_encode($content['taxAddress']);

            // Reinicializamos el Request con el nuevo contenido serializado
            $request->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                json_encode($content)
            );
        }
    }
}
