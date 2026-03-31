<?php

namespace App\Controller\Api;

use App\Entity\BarberSchedule;
use App\Entity\BarberService;
use App\Entity\MasterProduct;
use App\Entity\ServiceType;
use App\Form\Type\MasterProductFormType;
use App\Repository\MasterProductRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

        // Filtro por si esta activo (?isActive=true)
        if ($request->query->has('isActive')) {
            $value = $request->query->getBoolean('isActive');
            $qb->andWhere('u.isActive = :isActive')
                ->setParameter('isActive', $value);
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
            'u.vatRate',
            'u.barcode',
            'u.price',
            'u.uom',
            'u.isInventoriable',
            'u.image',
            'st.id as serviceTypeId',
            'st.name as serviceTypeName'
        ];
    }

    protected function getSearchFields(): array
    {
        return ['u.name', 'u.description', 'u.barcode'];
    }

    public function list(Request $request, $repository): JsonResponse
    {
        // 1. Llamamos al método list del padre para obtener la respuesta original
        $response = parent::list($request, $repository);

        // 2. Decodificamos el contenido para manipular los datos
        $data = json_decode($response->getContent(), true);

        $baseUrl = $request->getSchemeAndHttpHost();

        // 3. Formateamos el precio y la imagen en los resultados
        if (isset($data['results'])) {
            $data['results'] = array_map(function ($item) use ($baseUrl) {
                if (isset($item['price'])) {
                    // Aplicamos el formato: 1,000.00
                    $item['price'] = number_format((float)$item['price'], 2, '.', ',');
                }
                if (isset($item['image']) && $item['image']) {
                    $item['image'] = $baseUrl . $item['image'];
                } else {
                    $item['image'] = $baseUrl . '/uploads/products/placeholder.png';
                }
                return $item;
            }, $data['results']);
        }

        // 4. Retornamos la nueva respuesta con los datos formateados
        return new JsonResponse($data, $response->getStatusCode());
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
        $extraColumns = ['price', 'image'];
        return $masterProductRepository->getAllToSelect($extraColumns);
    }

    #[Rest\Post('/master_product')]
    public function create(Request $request): JsonResponse
    {
        return $this->handleSave($request, new MasterProduct(), "Producto maestro creado correctamente");
    }

    #[Rest\Put('/master_product/{id}')]
    public function update(Request $request, MasterProduct $id): JsonResponse
    {
        return $this->handleSave($request, $id, "Producto maestro actualizado correctamente");
    }

    private function handleSave(Request $request, MasterProduct $entity, string $successMessage): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Handle image base64
        if (isset($data['imageBase64']) && !empty($data['imageBase64'])) {
            $fileUrl = $this->saveBase64Image($data['imageBase64'], 'product');
            if ($fileUrl) {
                $entity->setImage($fileUrl);
            }
        } elseif (isset($data['removeImage']) && $data['removeImage'] === true) {
            $entity->setImage(null);
        }

        // Re-initialize request for processForm without images-related data to avoid form confusion (though already ignored by FormType)
        unset($data['imageBase64'], $data['removeImage']);

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            json_encode($data)
        );

        return $this->processForm($request, $entity, $successMessage);
    }

    private function saveBase64Image(string $base64String, string $prefix): ?string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $match)) {
            $data = substr($base64String, strpos($base64String, ',') + 1);
            $type = strtolower($match[1]);

            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return null;
            }

            $data = base64_decode($data);
            if ($data === false) {
                return null;
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = $prefix . '_' . uniqid() . '.' . $type;
            file_put_contents($uploadDir . $fileName, $data);

            return '/uploads/products/' . $fileName;
        }

        return null;
    }

    #[Rest\Delete('/master_product/{id}')]
    public function remove(MasterProduct $id): JsonResponse
    {
        return $this->delete($id);
    }

    #[Rest\Get('/master_product/{id}')]
    public function get(MasterProduct $id, Request $request): JsonResponse
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

        // 5. Ajuste para 'isInventoriable'
        if (isset($data['inventoriable']) && !isset($data['isInventoriable'])) {
            $data['isInventoriable'] = $data['inventoriable'];
            unset($data['inventoriable']);
        }

        // 5. Aprovechamos para formatear el precio como en el método list
        if (isset($data['price'])) {
            $data['price'] = number_format((float)$data['price'], 2, '.', ',');
        }

        if (isset($data['image']) && $data['image']) {
            $baseUrl = $request->getSchemeAndHttpHost();
            $data['image'] = $baseUrl . $data['image'];
        } else {
            $baseUrl = $request->getSchemeAndHttpHost();
            $data['image'] = $baseUrl . '/uploads/products/placeholder.png';
        }

        // 6. Retornamos la respuesta ya corregida
        return new JsonResponse($data, $response->getStatusCode());
    }

    #[Rest\Get('/service/public-list')]
    public function publicList(Request $request): JsonResponse
    {
        $branchId = $request->query->get('branchId');
        if (!$branchId) {
            return $this->json(['message' => 'branchId is required'], Response::HTTP_BAD_REQUEST);
        }

        $qb = $this->entityManager->getRepository(MasterProduct::class)->createQueryBuilder('mp');
        $qb->select('mp.id', 'mp.name', 'st.name as category', 'mp.description', 'mp.price', 'mp.image')
            ->addSelect('COALESCE(AVG(bs.durationOverrideMinutes), 40) as avgDuration')
            ->join(ServiceType::class, 'st', 'WITH', 'mp.serviceType = st.id')
            ->join(BarberService::class, 'bs', 'WITH', 'bs.product = mp.id')
            ->join(BarberSchedule::class, 'bsch', 'WITH', 'bsch.barber = bs.barber')
            ->where('bsch.branch = :branchId')
            ->andWhere('mp.isActive = :active')
            ->andWhere('mp.deletedAt IS NULL')
            ->andWhere('bs.isActive = :active')
            ->andWhere('bs.deletedAt IS NULL')
            ->andWhere('mp.isInventoriable = :notInventoriable')
            ->setParameter('branchId', $branchId)
            ->setParameter('active', true)
            ->setParameter('notInventoriable', false)
            ->groupBy('mp.id, mp.name, st.name, mp.description, mp.price, mp.image');

        $services = $qb->getQuery()->getResult();

        $baseUrl = $request->getSchemeAndHttpHost();
        $result = array_map(fn($s) => [
            'id' => $s['id'],
            'name' => $s['name'],
            'category' => $s['category'],
            'description' => $s['description'],
            'duration' => (int) round((float) $s['avgDuration']),
            'price' => number_format((float)$s['price'], 2, '.', ','),
            'image' => $s['image'] ? $baseUrl . $s['image'] : $baseUrl . '/uploads/products/placeholder.png',
            'popular' => false,
        ], $services);

        return $this->json($result, Response::HTTP_OK);
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
