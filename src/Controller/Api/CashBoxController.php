<?php

namespace App\Controller\Api;

use App\Entity\CashBox;
use App\Entity\CashBoxSession;
use App\Entity\Sale;
use App\Enum\CashBoxSessionStatus;
use App\Form\Type\CashBoxFormType;
use App\Repository\CashBoxRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CashBoxController extends BaseController
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected Security               $security
    )
    {
        // Llama al constructor del BaseController
        parent::__construct($entityManager, $security);
    }

    protected function getEntityClass(): string
    {
        return CashBox::class;
    }

    protected function getFormTypeClass(): string
    {
        return CashBoxFormType::class;
    }


    // Sobrescribimos para añadir el Join y un filtro extra
    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.branch', 'c'); // 'u' siempre es la entidad principal
        $qb->leftJoin('c.company', 'o');

        // Ejemplo: Filtro opcional por compañía si viene en la URL (?branch=1)
        if ($branchId = $request->query->get('branchId')) {
            $qb->andWhere('c.id = :branchId')
                ->setParameter('branchId', $branchId);
        }
        if ($companyId = $request->query->get('companyId')) {
            $qb->andWhere('o.id = companyId')
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
            'c.name as branchName', // Atributo adicional de la relación
            'c.id as branchId',
            'o.name as companyName', // Atributo adicional de la relación
            'o.id as companyId',
            'u.currentFolio',
            'u.ticketSerie'
        ];
    }

    #[Rest\Get('/cash_box')]
    public function index(Request $request, CashBoxRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Get('/cash_box/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(CashBoxRepository $cashBoxRepository)
    {
        return $cashBoxRepository->getAllToSelect();
    }

    #[Rest\Post('/cash_box')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new CashBox(), "Caja creada correctamente");
    }

    #[Rest\Put('/cash_box/{id}')]
    public function update(Request $request, CashBox $id): JsonResponse
    {
        return $this->processForm($request, $id, "Caja actualizada correctamente");
    }

    #[Rest\Delete('/cash_box/{id}')]
    public function remove(CashBox $id): mixed
    {
        $sales = $this->entityManager->getRepository(Sale::class)->count(['cashBox' => $id]);
        if ($sales > 0) {
            return $this->json([
                'message' => "No se puede eliminar la caja porque tiene ventas asociadas",
            ], Response::HTTP_BAD_REQUEST);
        }

        $cashboxSessions = $this->entityManager->getRepository(CashBoxSession::class)->count(['cashBox' => $id, 'status' => CashBoxSessionStatus::OPEN]);
        if ($cashboxSessions > 0) {
            return $this->json([
                'message' => "No se puede eliminar la caja porque tiene sesiones de caja asociadas",
            ], Response::HTTP_BAD_REQUEST);
        }
        return $this->delete($id);
    }

    #[Rest\Get('/cash_box/{id}')]
    public function get(CashBox $id): mixed
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
