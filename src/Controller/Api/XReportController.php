<?php

namespace App\Controller\Api;

use App\Entity\CashBoxMovement;
use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\XReport;
use App\Entity\XReportDetail;
use App\Enum\CashBoxSessionStatus;
use App\Enum\PaymentTypeEnum;
use App\Repository\CashBoxSessionRepository;
use App\Repository\CashBoxMovementRepository;
use App\Repository\XReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/x-reports')]
class XReportController extends AbstractController
{
    #[Route('/generate', name: 'app_x_report_generate', methods: ['POST'])]
    public function generate(
        Request                  $request,
        CashBoxSessionRepository $cashBoxSessionRepository,
        XReportRepository        $reportRepo,
        EntityManagerInterface   $em,
        UserInterface            $user
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // 1. Validar sesión abierta
        $session = $cashBoxSessionRepository->findOneBy([
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ]);

        if (!$session) {
            return $this->json(['error' => 'No tiene una sesión de la caja activa'], 404);
        }

        // 2. Mapeo de JSON a Enums
        $paymentMapping = [
            '3' => PaymentTypeEnum::CASH,
            '2' => PaymentTypeEnum::CARD,
            '1' => PaymentTypeEnum::TRAMSFER,
        ];

        // 3. Crear encabezado del reporte
        $report = new XReport();
        $report->setCashSession($session);
        $report->setUser($user);
        $report->setCreatedBy($user->getId());
        $report->setUpdatedBy($user->getId());
        $report->setXReportDate(new \DateTime());
        $report->setReportNumber($reportRepo->count(['cashSession' => $session]) + 1);
        $report->setObservations($data['observations'] ?? 'Reporte Corte X');

        $saleRepository = $em->getRepository(Sale::class);
        $paymentTypeRepo = $em->getRepository(PaymentType::class);
        $movementRepo = $em->getRepository(CashBoxMovement::class);

        // 4. Procesar dinámicamente cada método de pago
        foreach ($paymentMapping as $jsonKey => $enumValue) {
            $paymentTypeEntity = $paymentTypeRepo->find($enumValue->value);
            if (!$paymentTypeEntity) continue;

            // Obtener ventas del sistema para este método de pago
            $systemAmount = $saleRepository->getSummaryByPaymentType($session, $enumValue->value);

            // Lógica específica para CASH: (Ventas + Inicial) - Extracciones
            if ($enumValue === PaymentTypeEnum::CASH) {
                $systemAmount = bcadd($systemAmount, $session->getInitialAmount(), 2);
                $withdrawals = $movementRepo->getTotalWithdrawals($session);
                $systemAmount = bcsub($systemAmount, $withdrawals, 2);
            }

            $detail = new XReportDetail();
            $detail->setPaymentType($paymentTypeEntity);
            $detail->setSystemAmount($systemAmount);
            $detail->setDeclaredAmount((string)($data[$jsonKey] ?? '0.00'));
            $detail->setCreatedBy($user->getId());
            $detail->setUpdatedBy($user->getId());

            // El cálculo de la diferencia del detalle ocurre en su PrePersist
            $report->addDetail($detail);
        }

        // Al hacer flush, el PrePersist de XReport ejecutará syncTotalsFromDetails()
        $em->persist($report);
        $em->flush();

        // 5. Construir el desglose detallado para la respuesta
        $detailsResponse = [];
        foreach ($report->getDetails() as $detail) {
            $detailsResponse[] = [
                'payment_type' => $detail->getPaymentType()->getName(),
                'system_amount' => $detail->getSystemAmount(),
                'declared_amount' => $detail->getDeclaredAmount(),
                'difference' => $detail->getDifference()
            ];
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $report->getId(),
                'report_number' => $report->getReportNumber(),
                'date_time' => $report->getXReportDate()->format('Y-m-d H:i:s'),
                'totals' => [
                    'system_total' => $report->getSystemTotal(),
                    'declared_total' => $report->getDeclaredTotal(),
                    'difference' => $report->getDifference()
                ],
                'details' => $detailsResponse
            ]
        ], 201);
    }
}
