<?php

namespace App\License\Controller;

use App\License\Entity\LicLicenseType;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Rest\Route('/license-type')]
final class LicLicenseTypeController extends AbstractFOSRestController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Rest\Get('')]
    public function index(): JsonResponse
    {
        $types = $this->entityManager->getRepository(LicLicenseType::class)->findBy([], ['name' => 'ASC']);
        return $this->json($types, Response::HTTP_OK, [], ['groups' => ['license:read']]);
    }

    #[Rest\Post('')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $type = new LicLicenseType();
        return $this->handleSave($type, $data, Response::HTTP_CREATED);
    }

    #[Rest\Put('/{id}')]
    public function update(Request $request, int $id): JsonResponse
    {
        $type = $this->entityManager->getRepository(LicLicenseType::class)->find($id);
        if (!$type) {
            return $this->json(['message' => 'Tipo de licencia no encontrado'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true);
        return $this->handleSave($type, $data, Response::HTTP_OK);
    }

    #[Rest\Delete('/{id}')]
    public function delete(int $id): JsonResponse
    {
        $type = $this->entityManager->getRepository(LicLicenseType::class)->find($id);
        if (!$type) {
            return $this->json(['message' => 'Tipo de licencia no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        $this->entityManager->remove($type);
        $this->entityManager->flush();
        
        return $this->json(['message' => 'Tipo de licencia eliminado correctamente']);
    }

    private function handleSave(LicLicenseType $type, array $data, int $status): JsonResponse
    {
        if (isset($data['name'])) $type->setName($data['name']);
        if (isset($data['code'])) $type->setCode($data['code']);
        
        $type->setMaxActivations((int)($data['maxActivations'] ?? $type->getMaxActivations()));
        $type->setMaxBranches((int)($data['maxBranches'] ?? $type->getMaxBranches()));
        $type->setMaxBarbers((int)($data['maxBarbers'] ?? $type->getMaxBarbers()));
        $type->setDurationDays((int)($data['durationDays'] ?? $type->getDurationDays()));
        
        if (isset($data['isActive'])) $type->setIsActive($data['isActive']);

        try {
            $this->entityManager->persist($type);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error de base de datos: ' . $e->getMessage(),
                'trace' => 'Verifique si ejecutó las migraciones (php bin/console doctrine:migrations:migrate)'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($type, $status, [], ['groups' => ['license:read']]);
    }
}
