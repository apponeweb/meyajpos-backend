<?php

namespace App\License\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\License\Entity\LicLicense;
use App\License\Form\LicenseFormType;
use App\License\Repository\LicLicenseRepository;
use App\License\Repository\LicSystemRepository;
use App\License\Service\LicenseGeneratorService;
use App\License\Service\LicenseService;
use App\Repository\CompanyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Rest\Route('/license')]
final class LicenseController extends AbstractFOSRestController
{
    public function __construct(
        private readonly LicLicenseRepository  $licenseRepository,
        private readonly LicSystemRepository   $systemRepository,
        private readonly LicenseService        $licenseService,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security              $security,
        private readonly UserRepository        $userRepository,
        private readonly CompanyRepository     $companyRepository,
        private readonly LicenseGeneratorService $generatorService,
    ) {}

    #[Rest\Get('')]
    public function index(Request $request): JsonResponse
    {
        $search  = $request->query->get('search');
        $status  = $request->query->get('status');
        $current  = $request->query->getInt('current', 1);
        $pageSize = $request->query->getInt('pageSize', 10);

        $qb = $this->licenseRepository->getListQueryBuilder($search, $status);

        $countQb = clone $qb;
        $total = (int)$countQb->select('COUNT(DISTINCT l.id)')->getQuery()->getSingleScalarResult();

        $results = $qb->select('l', 'u', 'c', 'ls', 's')
            ->setFirstResult(($current - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->orderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult();

        $formatted = array_map(
            fn(LicLicense $l) => $this->licenseService->formatLicenseForResponse($l),
            $results
        );

        return $this->json([
            'total'    => $total,
            'results'  => $formatted,
            'current'  => $current,
            'pageSize' => $pageSize,
        ]);
    }

    #[Rest\Get('/dashboard')]
    public function dashboard(): JsonResponse
    {
        return $this->json($this->licenseRepository->getDashboardStats());
    }

    #[Rest\Get('/systems')]
    public function systems(): JsonResponse
    {
        $systems = $this->systemRepository->findBy(['isActive' => true], ['name' => 'ASC']);
        $result = array_map(fn($s) => [
            'id'          => $s->getId(),
            'code'        => $s->getCode(),
            'name'        => $s->getName(),
            'description' => $s->getDescription(),
            'isActive'    => $s->isActive(),
        ], $systems);

        return $this->json($result);
    }

    #[Rest\Get('/systems/all')]
    public function allSystems(): JsonResponse
    {
        $systems = $this->systemRepository->findBy([], ['name' => 'ASC']);
        $result = array_map(fn($s) => [
            'id'          => $s->getId(),
            'code'        => $s->getCode(),
            'name'        => $s->getName(),
            'description' => $s->getDescription(),
            'isActive'    => $s->isActive(),
        ], $systems);

        return $this->json($result);
    }

    #[Rest\Post('/systems/{id}/toggle')]
    public function toggleSystem(int $id): JsonResponse
    {
        $system = $this->systemRepository->find($id);
        if (!$system) {
            return $this->json(['message' => 'Sistema no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $system->setIsActive(!$system->isActive());
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Estado del sistema actualizado correctamente.',
            'isActive' => $system->isActive(),
        ]);
    }

    #[Rest\Get('/users-for-select')]
    public function usersForSelect(): JsonResponse
    {
        return $this->json($this->userRepository->getAllForLicense());
    }

    #[Rest\Get('/companies-for-select')]
    public function companiesForSelect(): JsonResponse
    {
        return $this->json($this->companyRepository->getAllToSelect());
    }

    #[Rest\Get('/types-for-select')]
    public function typesForSelect(): JsonResponse
    {
        $types = $this->entityManager->getRepository(\App\License\Entity\LicLicenseType::class)->findBy(['isActive' => true], ['name' => 'ASC']);
        return $this->json(array_map(fn($t) => [
            'id' => $t->getId(), 
            'name' => $t->getName(),
            // Enviamos los límites para que el frontend pueda auto-completar
            'maxActivations' => $t->getMaxActivations(),
            'maxBranches' => $t->getMaxBranches(),
            'maxBarbers' => $t->getMaxBarbers(),
            'durationDays' => $t->getDurationDays(),
        ], $types));
    }

    #[Rest\Get('/generate-key')]
    public function generateKey(): JsonResponse
    {
        return $this->json(['key' => $this->generatorService->generateKey()]);
    }

    #[Rest\Get('/{id}')]
    public function show(int $id): JsonResponse
    {
        $license = $this->licenseRepository->findWithDetails($id);
        if (!$license) {
            return $this->json(['message' => 'Licencia no encontrada'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->licenseService->formatLicenseForResponse($license));
    }

    #[Rest\Post('')]
    public function create(Request $request): JsonResponse
    {
        $license = new LicLicense();
        return $this->handleForm($request, $license, Response::HTTP_CREATED, 'Licencia creada correctamente.');
    }

    #[Rest\Put('/{id}')]
    public function update(Request $request, int $id): JsonResponse
    {
        $license = $this->licenseRepository->find($id);
        if (!$license) {
            return $this->json(['message' => 'Licencia no encontrada'], Response::HTTP_NOT_FOUND);
        }
        return $this->handleForm($request, $license, Response::HTTP_OK, 'Licencia actualizada correctamente.');
    }

    #[Rest\Delete('/{id}')]
    public function delete(int $id): JsonResponse
    {
        $license = $this->licenseRepository->find($id);
        if (!$license) {
            return $this->json(['message' => 'Licencia no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($license);
        $this->entityManager->flush();

        return $this->json(['message' => 'Licencia eliminada correctamente.']);
    }

    #[Rest\Post('/activate')]
    public function activate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = $data['email'] ?? null;
        $key   = $data['licenseKey'] ?? null;
        $hwId  = $data['hardwareId'] ?? null;

        if (!$email || !$key) {
            return $this->json(['message' => 'Email y Clave de Licencia son requeridos.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['message' => 'Usuario no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $license = $this->licenseRepository->findOneBy(['user' => $user, 'licenseKey' => $key]);
        if (!$license) {
            return $this->json(['message' => 'Clave de licencia inválida para este usuario.'], Response::HTTP_NOT_FOUND);
        }

        if (!$license->isActive()) {
            return $this->json(['message' => 'Esta licencia se encuentra inactiva. Contacte al administrador.'], Response::HTTP_FORBIDDEN);
        }

        if ($license->getActivatedAt() !== null) {
            // Ya fue activada. Verificamos si es el mismo hardware
            if ($hwId && in_array($hwId, $license->getHardwareIds() ?? [])) {
                return $this->json(['message' => 'Esta licencia ya ha sido activada en este dispositivo.'], Response::HTTP_BAD_REQUEST);
            }

            // Si tiene activaciones disponibles, permitimos añadir este HW
            if ($hwId && count($license->getHardwareIds() ?? []) < $license->getMaxActivations()) {
                $license->addHardwareId($hwId);
                $this->entityManager->flush();
                return $this->json(['message' => 'Licencia activada en este nuevo dispositivo.', 'license' => $this->licenseService->formatLicenseForResponse($license)]);
            }

            return $this->json(['message' => 'Esta licencia ya ha sido activada en el máximo de dispositivos permitidos.'], Response::HTTP_BAD_REQUEST);
        }

        // Activación por primera vez
        $license->setActivatedAt(new \DateTime());
        if ($hwId) {
            $license->addHardwareId($hwId);
        }
        
        $this->entityManager->flush();

        return $this->json([
            'message' => '¡Licencia activada correctamente!',
            'license' => $this->licenseService->formatLicenseForResponse($license)
        ]);
    }

    private function handleForm(Request $request, LicLicense $license, int $statusCode, string $successMessage): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        // Resolver user, company y type manualmente
        $userId    = isset($data['user'])    ? (int)$data['user']    : null;
        $companyId = isset($data['company']) ? (int)$data['company'] : null;
        $typeId    = isset($data['type'])    ? (int)$data['type']    : null;

        if (!$userId || !$companyId || !$typeId) {
            return $this->json([
                'message' => 'Datos inválidos',
                'errors'  => ['user' => ['Requerido'], 'company' => ['Requerido'], 'type' => ['Requerido']],
            ], Response::HTTP_BAD_REQUEST);
        }

        $userEntity    = $this->entityManager->getReference(User::class, $userId);
        $companyEntity = $this->entityManager->getReference(Company::class, $companyId);
        $typeEntity    = $this->entityManager->getRepository(\App\License\Entity\LicLicenseType::class)->find($typeId);

        if (!$typeEntity) {
            return $this->json(['message' => 'Tipo de licencia inválido'], Response::HTTP_BAD_REQUEST);
        }

        // Si cambiamos el tipo de licencia, actualizamos límites si no vienen en la data
        if ($license->getType() && $license->getType()->getId() !== $typeEntity->getId()) {
            if (!isset($data['maxActivations'])) $license->setMaxActivations($typeEntity->getMaxActivations());
            if (!isset($data['maxBranches']))    $license->setMaxBranches($typeEntity->getMaxBranches());
            if (!isset($data['maxBarbers']))     $license->setMaxBarbers($typeEntity->getMaxBarbers());
            if (!isset($data['durationDays']))   $license->setDurationDays($typeEntity->getDurationDays());
        }

        $license->setUser($userEntity);
        $license->setCompany($companyEntity);
        $license->setType($typeEntity);

        // Si es nueva, heredar límites del tipo si no se proporcionan
        if ($license->getId() === null) {
            if (!isset($data['maxActivations'])) $license->setMaxActivations($typeEntity->getMaxActivations());
            if (!isset($data['maxBranches']))    $license->setMaxBranches($typeEntity->getMaxBranches());
            if (!isset($data['maxBarbers']))     $license->setMaxBarbers($typeEntity->getMaxBarbers());
            if (!isset($data['durationDays']))   $license->setDurationDays($typeEntity->getDurationDays());
        }

        // Quitar campos manuales del array para el formulario
        $formData = array_diff_key($data, array_flip(['user', 'company', 'type', 'systems']));

        $form = $this->createForm(LicenseFormType::class, $license);
        $form->submit($formData, $request->getMethod() !== 'PATCH');

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->json([
                'message' => 'Datos inválidos',
                'errors'  => $this->getFormErrors($form),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $authUser   = $this->security->getUser();
            $authUserId = ($authUser && method_exists($authUser, 'getId')) ? (int)$authUser->getId() : null;

            if ($license->getId() === null) {
                $license->setCreatedBy($authUserId);
                // Generar clave si no existe
                if (!$license->getLicenseKey()) {
                    $license->setLicenseKey($this->generatorService->generateKey());
                }
            }
            $license->setUpdatedBy($authUserId);

            $this->entityManager->persist($license);
            $this->entityManager->flush();

            // Sincronizar sistemas habilitados
            if (isset($data['systems']) && is_array($data['systems'])) {
                $this->licenseService->syncLicenseSystems($license, $data['systems']);
                $this->entityManager->flush();
            }

            return $this->json([
                'message' => $successMessage,
                'data'    => ['id' => $license->getId()],
            ], $statusCode);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error interno: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];
        foreach ($form->all() as $child) {
            $childErrors = [];
            foreach ($child->getErrors() as $error) {
                $childErrors[] = $error->getMessage();
            }
            if (!empty($childErrors)) {
                $errors[$child->getName()] = $childErrors;
            }
        }
        foreach ($form->getErrors() as $error) {
            $errors['_global'][] = $error->getMessage();
        }
        return $errors;
    }
}
