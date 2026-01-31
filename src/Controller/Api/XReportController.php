<?php

namespace App\Controller\Api;

use App\Entity\CashBoxMovement;
use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\XReport;
use App\Entity\XReportDetail;
use App\Enum\CashBoxSessionStatus;
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

        // 2. Crear encabezado del reporte
        $report = new XReport();
        $report->setCashSession($session);
        $report->setUser($user);
        $report->setCreatedBy($user->getId());
        $report->setUpdatedBy($user->getId());
        $report->setXReportDate(new \DateTime());
        $report->setReportNumber($reportRepo->count(['cashSession' => $session]) + 1);
        $report->setObservations($data['observations'] ?? 'Reporte Corte X Dinámico');

        $saleRepository = $em->getRepository(Sale::class);
        $paymentTypeRepo = $em->getRepository(PaymentType::class);
        $movementRepo = $em->getRepository(CashBoxMovement::class);

        // 3. Obtener TODOS los tipos de pago activos de la BD
        $allPaymentTypes = $paymentTypeRepo->findBy(['isActive' => true]);

        // 4. Procesar dinámicamente cada método de pago de la BD
        foreach ($allPaymentTypes as $paymentTypeEntity) {
            $ptId = $paymentTypeEntity->getId();

            // Obtener ventas del sistema para este ID de tipo de pago
            $systemAmount = $saleRepository->getSummaryByPaymentType($session, $ptId);

            // Lógica para Efectivo: Usamos la propiedad de la entidad isCash()
            if ($paymentTypeEntity->isCash()) {
                // 1. (Ventas + Inicial)
                $systemAmount = bcadd($systemAmount, $session->getInitialAmount(), 2);

                // 2. Sumar Depósitos (+ depositos)
                $deposits = $movementRepo->getTotalDeposits($session);
                $systemAmount = bcadd($systemAmount, $deposits, 2);

                // 3. Restar Extracciones (- Extracciones)
                $withdrawals = $movementRepo->getTotalWithdrawals($session);
                $systemAmount = bcsub($systemAmount, $withdrawals, 2);
            }

            $detail = new XReportDetail();
            $detail->setPaymentType($paymentTypeEntity);
            $detail->setSystemAmount($systemAmount);

            // Mapeamos el declarado buscando el ID en el JSON enviado
            // JSON esperado: {"1": "100.00", "2": "50.00", "observations": "..."}
            $detail->setDeclaredAmount((string)($data[$ptId] ?? '0.00'));

            $detail->setCreatedBy($user->getId());
            $detail->setUpdatedBy($user->getId());

            $report->addDetail($detail);
        }

        $em->persist($report);
        $em->flush();

        // 5. Construir respuesta (asegurando que getDetails() exista en XReport)
        $detailsResponse = [];
        // Nota: Si getDetails() falla, revisa el getter en la entidad XReport
        foreach ($report->getDetails() as $detail) {
            $detailsResponse[] = [
                'payment_type' => $detail->getPaymentType()->getName(),
                'is_cash' => $detail->getPaymentType()->isCash(),
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
                'date_time' => $report->getXReportDate()->format('d/m/Y H:i:s'),
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
