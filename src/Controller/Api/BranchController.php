<?php

namespace App\Controller\Api;

use App\Entity\Branch;
use App\Form\Type\BranchFormType;
use App\Repository\BranchRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class BranchController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Branch::class;
    }

    protected function getFormTypeClass(): string
    {
        return BranchFormType::class;
    }


    // Sobrescribimos para añadir el Join y un filtro extra
    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.company', 'c'); // 'u' siempre es la entidad principal

        // Ejemplo: Filtro opcional por compañía si viene en la URL (?companyId=1)
        if ($companyId = $request->query->get('companyId')) {
            $qb->andWhere('c.id = :companyId')
                ->setParameter('companyId', $companyId);
        }
    }

    // Sobrescribimos para añadir campos de la relación al JSON
    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.name',
            'u.description',
            'u.isActive',
            'u.acronym',
            'u.address',
            'c.name as companyName',
            'c.id as companyId'
        ];
    }

    #[Rest\Get('/branch')]
    public function index(Request $request, BranchRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Get('/branch/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(BranchRepository $branchRepository)
    {
        return $branchRepository->getAllToSelect();
    }

    #[Rest\Post('/branch')]
    public function create(Request $request): JsonResponse
    {
        $this->normalizeAddress($request);
        return $this->processForm($request, new Branch(), "Sucursal creada correctamente");
    }

    #[Rest\Put('/branch/{id}')]
    public function update(Request $request, Branch $id): JsonResponse
    {
        $this->normalizeAddress($request);
        return $this->processForm($request, $id, "Sucursal actualizada correctamente");
    }

    #[Rest\Delete('/branch/{id}')]
    public function remove(Branch $id): mixed
    {
        return $this->delete($id);
    }

    #[Rest\Get('/branch/{id}')]
    public function get(Branch $id): mixed
    {
        return $this->getDetails($id);
    }

    /**
     * Normaliza el campo taxAddress transformando el objeto JSON en un string
     * para que sea compatible con el campo TEXT de la entidad.
     */
    private function normalizeAddress(Request $request): void
    {
        $content = json_decode($request->getContent(), true);

        if (isset($content['address']) && is_array($content['address'])) {
            $content['address'] = json_encode($content['address']);

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
