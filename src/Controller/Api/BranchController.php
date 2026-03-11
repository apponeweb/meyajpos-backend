<?php

namespace App\Controller\Api;

use App\Entity\Branch;
use App\Entity\BranchHour;
use App\Entity\CashBox;
use App\Entity\Sale;
use App\Form\Type\BranchFormType;
use App\Repository\BranchRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
            'u.phone',
            'u.image',
            'u.rating',
            'u.reviewCount',
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

    #[Rest\Get('/branch/public-list')]
    public function publicList(Request $request, BranchRepository $repository): JsonResponse
    {
        $companyId = $request->query->get('company');
        if ($companyId) {
            $branches = $repository->findBy(['company' => $companyId, 'deletedAt' => null, 'isActive' => true]);
        } else {
            $branches = $repository->findBy(['deletedAt' => null, 'isActive' => true]);
        }


        $scheme = $request->getScheme();
        $host = $request->getHttpHost();
        $baseUrl = $scheme . '://' . $host;

        $result = [];
        $dayNames = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        ];

        foreach ($branches as $branch) {
            $hours = [];
            foreach ($dayNames as $idx => $name) {
                $hours[$name] = ['open' => false, 'entry' => '', 'exit' => ''];
            }

            $branchHours = $this->entityManager->getRepository(BranchHour::class)->findBy(['branch' => $branch]);
            foreach ($branchHours as $bh) {
                $dayName = $dayNames[$bh->getDayOfWeek()] ?? null;
                if ($dayName) {
                    $hours[$dayName] = [
                        'open' => true,
                        'entry' => $bh->getOpenTime()->format('g:i A'),
                        'exit' => $bh->getCloseTime()->format('g:i A')
                    ];
                }
            }

            $result[] = [
                'id' => $branch->getId(),
                'name' => $branch->getName(),
                'address' => $branch->getAddress(),
                'phone' => $branch->getPhone(),
                'image' => $branch->getImage() ? $baseUrl . $branch->getImage() : null,
                'rating' => $branch->getRating(),
                'reviewCount' => $branch->getReviewCount(),
                'hours' => $hours,
                'mapUrl' => 'https://maps.google.com/?q=' . urlencode($branch->getAddress()),
            ];
        }

        return $this->json($result, Response::HTTP_OK);
    }

    #[Rest\Post('/branch')]
    public function create(Request $request): JsonResponse
    {
        return $this->handleSave($request, new Branch(), "Sucursal creada correctamente");
    }

    #[Rest\Put('/branch/{id}')]
    public function update(Request $request, Branch $id): JsonResponse
    {
        return $this->handleSave($request, $id, "Sucursal actualizada correctamente");
    }

    private function handleSave(Request $request, Branch $entity, string $successMessage): JsonResponse
    {
        $this->normalizeAddress($request, 'address');
        $data = json_decode($request->getContent(), true);

        // Handle image base64
        if (isset($data['imageBase64']) && !empty($data['imageBase64'])) {
            $fileUrl = $this->saveBase64Image($data['imageBase64'], 'branch');
            if ($fileUrl) {
                $data['image'] = $fileUrl;
                $entity->setImage($fileUrl);
            }
        } elseif (isset($data['removeImage']) && $data['removeImage'] === true) {
            $data['image'] = null;
            $entity->setImage(null);
        }

        unset(
            $data['imageBase64'],
            $data['removeImage']
        );

        // Re-initialize request for processForm
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

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/branches/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = $prefix . '_' . uniqid() . '.' . $type;
            file_put_contents($uploadDir . $fileName, $data);

            return '/uploads/branches/' . $fileName;
        }

        return null;
    }

    #[Rest\Delete('/branch/{id}')]
    public function remove(Branch $id): mixed
    {
        $cashbox = $this->entityManager->getRepository(CashBox::class)->findBy(['branch' => $id]);
        if ($cashbox) {
            foreach ($cashbox as $box) {
                $sales = $this->entityManager->getRepository(Sale::class)->count(['cashBox' => $box->getId()]);
                if ($sales > 0) {
                    return $this->json([
                        'message' => "No se puede eliminar la sucursal, porque tiene al menos una caja con ventas asociadas",
                    ], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        return $this->delete($id);
    }

    #[Rest\Get('/branch/{id}')]
    public function get(Branch $id): mixed
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
