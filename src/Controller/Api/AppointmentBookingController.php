<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Service\Appointment\AppointmentBookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/appointments')]
class AppointmentBookingController extends BaseController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        private readonly AppointmentBookingService $appointmentBookingService,
    ) {
        parent::__construct($entityManager, $security);
    }

    protected function getEntityClass(): string
    {
        return Appointment::class;
    }

    protected function getFormTypeClass(): string
    {
        // No usamos un formulario estándar de Symfony para este flujo multi-entidad.
        return '';
    }

    #[Route('/book', name: 'api_appointment_book', methods: ['POST'])]
    public function book(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(
                ['message' => 'JSON inválido'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $result = $this->appointmentBookingService->book($data);

            return $this->json([
                'message' => 'Cita reservada con éxito',
                'data' => [
                    'appointmentId' => $result['appointmentId'],
                    'customer' => $result['customer'],
                ],
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'message' => 'Datos inválidos para realizar la reserva',
                'detail' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->json([
                'message' => 'Error al realizar la reserva',
                'detail' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }
    }
}
