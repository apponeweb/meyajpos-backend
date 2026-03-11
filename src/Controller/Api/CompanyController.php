<?php

namespace App\Controller\Api;

use App\Entity\Company;
use App\Form\Type\CompanyFormType;
use App\Form\Type\CompanyPolicyFormType;
use App\Repository\CompanyRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;

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
            'u.tagline',
            'u.email',
            'u.coverImage',
            'u.logo',
            'u.socialNetworks',
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
        return $this->handleSave($request, new Company(), "Empresa creada correctamente");
    }

    #[Rest\Put('/company/{id}')]
    public function update(Request $request, Company $id): JsonResponse
    {
        return $this->handleSave($request, $id, "Empresa actualizada correctamente");
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

    #[Rest\Put('/company/{id}/policy')]
    public function updatePolicy(Request $request, Company $id): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $form = $this->createForm(CompanyPolicyFormType::class, $id);
        $form->submit($data ?? $request->request->all(), false);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $now = new \DateTime();
                $user = $this->security->getUser();
                $userId = ($user && method_exists($user, 'getId')) ? (int) $user->getId() : null;

                if ($userId && method_exists($id, 'setUpdatedBy'))
                    $id->setUpdatedBy($userId);
                if (method_exists($id, 'setUpdatedAt'))
                    $id->setUpdatedAt($now);

                $this->entityManager->persist($id);
                $this->entityManager->flush();

                return $this->json([
                    'message' => 'Políticas actualizadas correctamente',
                    'data' => ['id' => $id->getId()]
                ], JsonResponse::HTTP_OK);
            } catch (\Exception $e) {
                return $this->json(['message' => 'Error: ' . $e->getMessage()], 500);
            }
        }

        return $this->json([
            'message' => 'Validación fallida',
            'errors' => $this->getFormErrors($form)
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    #[Rest\Get('/company/{id}/info')]
    public function info(Request $request, Company $id): JsonResponse
    {
        $company = $id;

        $socialNetworks = [];
        if ($company->getSocialNetworks()) {
            $decoded = json_decode($company->getSocialNetworks(), true);
            if (is_array($decoded)) {
                $socialNetworks = $decoded;
            }
        }

        $scheme = $request->getScheme();
        $host = $request->getHttpHost();
        $baseUrl = $scheme . '://' . $host;

        $coverImageFull = $company->getCoverImage() ? $baseUrl . $company->getCoverImage() : null;
        $logoFull = $company->getLogo() ? $baseUrl . $company->getLogo() : null;

        $data = [
            'name' => $company->getName(),
            'tagline' => $company->getTagline(),
            'phone' => $company->getPhone(),
            'email' => $company->getEmail(),
            'instagram' => $socialNetworks['instagram'] ?? null,
            'facebook' => $socialNetworks['facebook'] ?? null,
            'tiktok' => $socialNetworks['tiktok'] ?? null,
            'whatsapp' => $socialNetworks['whatsapp'] ?? null,
            'coverImage' => $coverImageFull,
            'logo' => $logoFull,
            'cancellationPolicy' => $company->getCancellationPolicy(),
            'privacyPolicy' => $company->getPrivacyPolicy(),
        ];

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }

    private function handleSave(Request $request, Company $entity, string $successMessage): JsonResponse
    {
        $this->normalizeAddress($request, 'taxAddress');
        $this->normalizeAddress($request, 'socialNetworks');

        $data = json_decode($request->getContent(), true);

        // Handle coverImage base64
        if (isset($data['coverImageBase64']) && !empty($data['coverImageBase64'])) {
            $fileUrl = $this->saveBase64Image($data['coverImageBase64'], 'cover');
            if ($fileUrl) {
                $data['coverImage'] = $fileUrl;
                $entity->setCoverImage($fileUrl);
            }
        } elseif (isset($data['removeCoverImage']) && $data['removeCoverImage'] === true) {
            $data['coverImage'] = null;
            $entity->setCoverImage(null);
        }

        // Handle logo base64
        if (isset($data['logoBase64']) && !empty($data['logoBase64'])) {
            $fileUrl = $this->saveBase64Image($data['logoBase64'], 'logo');
            if ($fileUrl) {
                $data['logo'] = $fileUrl;
                $entity->setLogo($fileUrl);
            }
        } elseif (isset($data['removeLogo']) && $data['removeLogo'] === true) {
            $data['logo'] = null;
            $entity->setLogo(null);
        }

        unset(
            $data['coverImageBase64'],
            $data['removeCoverImage'],
            $data['logoBase64'],
            $data['removeLogo']
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

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/companies';
            $fs = new Filesystem();
            if (!$fs->exists($uploadDir)) {
                $fs->mkdir($uploadDir, 0755);
            }

            $fileName = uniqid($prefix . '_') . '.' . $type;
            $filePath = $uploadDir . '/' . $fileName;

            file_put_contents($filePath, $data);

            return '/uploads/companies/' . $fileName;
        }

        return null;
    }
}
