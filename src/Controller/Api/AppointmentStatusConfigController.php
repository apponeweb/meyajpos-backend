<?php

namespace App\Controller\Api;

use App\Entity\AppointmentStatusConfig;
use App\Enum\AppointmentStatus;
use App\Repository\AppointmentStatusConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/appointment-status-config')]
class AppointmentStatusConfigController extends BaseController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        private AppointmentStatusConfigRepository $repository
    ) {
        parent::__construct($entityManager, $security);
    }

    protected function getEntityClass(): string
    {
        return AppointmentStatusConfig::class;
    }

    protected function getFormTypeClass(): string
    {
        return ''; // We'll handle manual update
    }

    #[Route('', name: 'api_appointment_status_config_list', methods: ['GET'])]
    public function getConfigs(): JsonResponse
    {
        $configs = $this->repository->findBy(['deletedAt' => null]);
        
        $results = [];
        $existingStatuses = [];
        
        foreach ($configs as $config) {
            $results[] = [
                'id' => $config->getId(),
                'status' => $config->getStatus(),
                'label' => $config->getLabel(),
                'color' => $config->getColor()
            ];
            $existingStatuses[] = $config->getStatus();
        }

        // Fill with defaults if missing
        foreach (AppointmentStatus::cases() as $status) {
            if (!in_array($status->value, $existingStatuses)) {
                $results[] = [
                    'id' => null,
                    'status' => $status->value,
                    'label' => $status->getLabel(),
                    'color' => $this->getDefaultColor($status)
                ];
            }
        }

        return $this->json($results, Response::HTTP_OK);
    }

    #[Route('/update', name: 'api_appointment_status_config_update', methods: ['POST'])]
    public function updateConfigs(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data)) {
            return $this->json(['message' => 'Datos inválidos'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($data as $item) {
            $statusValue = $item['status'];
            $config = $this->repository->findOneBy(['status' => $statusValue]);

            if (!$config) {
                $config = new AppointmentStatusConfig();
                $config->setStatus($statusValue);
                $this->entityManager->persist($config);
            }

            $config->setLabel($item['label'] ?? AppointmentStatus::from($statusValue)->getLabel());
            $config->setColor($item['color'] ?? $this->getDefaultColor(AppointmentStatus::from($statusValue)));
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Configuraciones actualizadas con éxito'], Response::HTTP_OK);
    }

    private function getDefaultColor(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::PENDING => '#fbbf24',    // Amber
            AppointmentStatus::CONFIRMED => '#3b82f6',  // Blue
            AppointmentStatus::CANCELLED => '#ef4444',  // Red
            AppointmentStatus::COMPLETED => '#10b981',  // Emerald
            AppointmentStatus::NO_SHOW => '#6b7280',    // Gray
            AppointmentStatus::IN_PROCESS => '#8b5cf6', // Violet
        };
    }
}
