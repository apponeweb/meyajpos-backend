<?php

namespace App\Controller\Api;

use App\Entity\CashBoxMovement;
use App\Entity\CashBoxSession;
use App\Entity\CommissionDetail;
use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\SaleDetail;
use App\Entity\Tip;
use App\Entity\XReport;
use App\Entity\XReportDetail;
use App\Enum\CashBoxSessionStatus;
use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use App\Enum\PaymentTypeEnum;
use App\Repository\CashBoxSessionRepository;
use App\Repository\CashBoxMovementRepository;
use App\Repository\SaleRepository;
use App\Repository\XReportRepository;
use App\Service\XReportService;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/x-reports')]
class XReportController extends AbstractController
{
    public function __construct(
        private readonly XReportService $xReportService
    )
    {
    }

    #[Route('/preview', name: 'app_x_report_preview', methods: ['GET'])]
    public function preview(XReportService $reportService): JsonResponse
    {
        try {
            $data = $reportService->getPreviewData();
            return $this->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            if ($statusCode === 404) {
                return $this->json(['error' => $e->getMessage()], 404);
            }
            return $this->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    #[Route('/generate', name: 'app_x_report_generate', methods: ['POST'])]
    public function generate(
        Request                  $request,
        CashBoxSessionRepository $cashBoxSessionRepository,
        XReportRepository        $reportRepo,
        EntityManagerInterface   $em,
        UserInterface            $user,
        XReportService           $reportService
    ): JsonResponse
    {
//        $report = $reportRepo->find(45);
//        $ticket = $this->xReportService->generateTicketData($report);
//        echo '<pre>';
//        print_r(json_decode($ticket, true));
//        die;

        $data = json_decode($request->getContent(), true);
        $session = $cashBoxSessionRepository->findOneBy(['user' => $user, 'status' => CashBoxSessionStatus::OPEN]);

        if (!$session) {
            return $this->json(['error' => 'No tiene una sesión de la caja activa'], 404);
        }

        $report = new XReport();
        $report->setCashSession($session);
        $report->setUser($user);
        $report->setCreatedBy($user->getId());
        $report->setUpdatedBy($user->getId());
        $report->setXReportDate(new \DateTime());
        $report->setReportNumber($reportRepo->count(['cashSession' => $session]) + 1);

        // Si envías observaciones fuera de los IDs, las capturamos; si no, valor por defecto
        $report->setObservations($data['observations'] ?? 'Corte X por tipo de pago');

        $paymentTypeRepo = $em->getRepository(PaymentType::class);
        $allPaymentTypes = $paymentTypeRepo->findBy(['isActive' => true]);

        foreach ($allPaymentTypes as $paymentTypeEntity) {
            $ptId = $paymentTypeEntity->getId();

            // 1. Calculamos lo que dice el sistema
            $systemAmount = $reportService->calculateSystemAmount($session, $paymentTypeEntity);

            // 2. Buscamos en el JSON el valor usando el ID como llave
            // Usamos (string)$ptId porque las llaves de JSON decodificado suelen ser strings
            $declaredValue = $data[(string)$ptId] ?? '0.00';

            $detail = new XReportDetail();
            $detail->setPaymentType($paymentTypeEntity);
            $detail->setSystemAmount($systemAmount);
            $detail->setDeclaredAmount((string)$declaredValue);
            $detail->setCreatedBy($user->getId());
            $detail->setUpdatedBy($user->getId());

            $report->addDetail($detail);
        }

        $em->persist($report);
        $em->flush();

        // --- Respuesta con el resumen ---
        $detailsResponse = [];
        foreach ($report->getDetails() as $detail) {
            $detailsResponse[] = [
                'payment_type' => $detail->getPaymentType()->getName(),
                'system_amount' => $detail->getSystemAmount(),
                'declared_amount' => $detail->getDeclaredAmount(),
                'difference' => $detail->getDifference()
            ];
        }
        $ticket = $this->xReportService->generateTicketData($report);
        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $report->getId(),
                'report_number' => $report->getReportNumber(),
                'totals' => [
                    'system_total' => number_format((float)$report->getSystemTotal(), 2, '.', ','),
                    'declared_total' => number_format((float)$report->getDeclaredTotal(), 2, '.', ','),
                    'difference' => number_format((float)$report->getDifference(), 2, '.', ','),
                ],
                'details' => $detailsResponse,
                'ticket' => $ticket
            ]
        ], 201);
    }
}
