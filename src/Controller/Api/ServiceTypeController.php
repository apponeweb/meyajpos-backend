<?php

namespace App\Controller\Api;

use App\Entity\CommissionDetail;
use App\Entity\Sale;
use App\Entity\BarberSchedule;
use App\Entity\BarberService;
use App\Entity\MasterProduct;
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
            'u.isActive',
            'u.isCourtesy'
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

        // 6. Retornamos la respuesta ya corregida
        return new JsonResponse($data, $response->getStatusCode());
    }

    #[Rest\Get('/category/public-list')]
    public function publicList(Request $request): JsonResponse
    {
        $branchId = $request->query->get('branchId');
        if (!$branchId) {
            return $this->json(['message' => 'branchId is required'], Response::HTTP_BAD_REQUEST);
        }

        $qb = $this->entityManager->getRepository(ServiceType::class)->createQueryBuilder('st');
        $qb->select('st.id', 'st.name', 'COUNT(mp.id) as count')
            ->join(MasterProduct::class, 'mp', 'WITH', 'mp.serviceType = st.id')
            ->join(BarberService::class, 'bs', 'WITH', 'bs.product = mp.id')
            ->join(BarberSchedule::class, 'bsch', 'WITH', 'bsch.barber = bs.barber')
            ->where('bsch.branch = :branchId')
            ->andWhere('st.isActive = :active')
            ->andWhere('st.deletedAt IS NULL')
            ->andWhere('mp.isActive = :active')
            ->andWhere('mp.deletedAt IS NULL')
            ->andWhere('bs.isActive = :active')
            ->andWhere('bs.deletedAt IS NULL')
            ->setParameter('branchId', $branchId)
            ->setParameter('active', true)
            ->groupBy('st.id', 'st.name');

        $categories = $qb->getQuery()->getResult();

        $result = array_map(fn($c) => [
            'id' => $c['id'],
            'name' => $c['name'],
            'count' => (int)$c['count']
        ], $categories);

        // Add 'Todos' if there are results
        if (count($result) > 0) {
            $totalCount = array_sum(array_column($result, 'count'));
            array_unshift($result, ['id' => 'all', 'name' => 'Todos', 'count' => $totalCount]);
        }

        return $this->json($result, Response::HTTP_OK);
    }
}
