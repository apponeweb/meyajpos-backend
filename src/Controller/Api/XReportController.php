<?php

namespace App\Controller\Api;

use App\Entity\CashBoxMovement;
use App\Entity\CashBoxSession;
use App\Entity\CommissionDetail;
use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\Tip;
use App\Entity\XReport;
use App\Entity\XReportDetail;
use App\Enum\CashBoxSessionStatus;
use App\Enum\CashMovementType;
use App\Repository\CashBoxSessionRepository;
use App\Repository\CashBoxMovementRepository;
use App\Repository\SaleRepository;
use App\Repository\XReportRepository;
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
    #[Route('/preview', name: 'app_x_report_preview', methods: ['GET'])]
    public function preview(
        CashBoxSessionRepository  $cashBoxSessionRepository,
        EntityManagerInterface    $em,
        UserInterface             $user,
        CashBoxMovementRepository $movementRepo,
        SaleRepository            $saleRepository
    ): JsonResponse
    {
        $session = $cashBoxSessionRepository->findOneBy(['user' => $user, 'status' => CashBoxSessionStatus::OPEN]);

        if (!$session) {
            return $this->json(['error' => 'No tiene una sesión de la caja activa'], 404);
        }

        $sales = $saleRepository->count(['cashBox' => $session->getCashBox()]);
        if ($sales == 0) {
            return $this->json([
                'message' => "No se puede generar un corte X por no hacer ventas realizadas en esta sesión.",
            ], Response::HTTP_BAD_REQUEST);
        }

        $paymentTypeRepo = $em->getRepository(PaymentType::class);
        $allPaymentTypes = $paymentTypeRepo->findBy(['isActive' => true]);

        $previewDetails = [];
        $totalSystem = '0.00';

        foreach ($allPaymentTypes as $paymentType) {
            $amount = $this->calculateSystemAmount($session, $paymentType, $em);

            $previewDetails[] = [
                'payment_type_id' => $paymentType->getId(),
                'payment_type_name' => $paymentType->getName(),
                'is_cash' => $paymentType->isCash(),
                'system_amount' => $amount
            ];

            $totalSystem = bcadd($totalSystem, $amount, 2);
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'session_id' => $session->getId(),
                'initial_amount' => $session->getInitialAmount(),
                'system_total' => $totalSystem,
                'total_deposits' => $movementRepo->getTotalDeposits($session),
                'total_extractions' => $movementRepo->getTotalWithdrawals($session),
                'details' => $previewDetails
            ]
        ]);
    }

    #[Route('/generate', name: 'app_x_report_generate', methods: ['POST'])]
    public function generate(
        Request                  $request,
        CashBoxSessionRepository $cashBoxSessionRepository,
        XReportRepository        $reportRepo,
        EntityManagerInterface   $em,
        UserInterface            $user
    ): JsonResponse
    {
//        $report = $reportRepo->find(24);
//        $session = $cashBoxSessionRepository->find(13);
//        $ticket = $this->generateXTicketData($report, $session, $user, $em);
//
//        echo '<pre>';
//        print_r($ticket);
//        die;
        // El JSON llega como {"3":"20", "2":"60.00", ...}
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
            $systemAmount = $this->calculateSystemAmount($session, $paymentTypeEntity, $em);

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
        $ticket = $this->generateXTicketData($report, $session, $user, $em);
        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $report->getId(),
                'report_number' => $report->getReportNumber(),
                'totals' => [
                    'system_total' => $report->getSystemTotal(),
                    'declared_total' => $report->getDeclaredTotal(),
                    'difference' => $report->getDifference()
                ],
                'details' => $detailsResponse,
                'ticket' => $ticket
            ]
        ], 201);
    }

    private function calculateSystemAmount(CashBoxSession $session, PaymentType $paymentType, EntityManagerInterface $em): string
    {
        $saleRepository = $em->getRepository(Sale::class);
        $movementRepo = $em->getRepository(CashBoxMovement::class);

        $amount = $saleRepository->getSummaryByPaymentType($session, $paymentType->getId());

        if ($paymentType->isCash()) {
            // (Ventas + Inicial + Depósitos)
            $amount = bcadd($amount, $session->getInitialAmount(), 2);
            $amount = bcadd($amount, $movementRepo->getTotalDeposits($session), 2);

            // - Retiros
            $amount = bcsub($amount, $movementRepo->getTotalWithdrawals($session), 2);
        }

        return $amount;
    }

    private function generateXTicketData(XReport $report, CashBoxSession $session, UserInterface $user, EntityManagerInterface $em)
    {
        $cashBox = $session->getCashBox();
        $company = $cashBox->getBranch()->getCompany();
        $movementRepo = $em->getRepository(CashBoxMovement::class);
        $tipRepo = $em->getRepository(Tip::class);
        $saleRepo = $em->getRepository(Sale::class);
        $now = new \DateTime();

        // 1. Clasificar Movimientos (Ingresos y Retiros)
        $movements = $movementRepo->findBy(['cashBoxSession' => $session]);
        $ingresos = [];
        $retiros = [];

        foreach ($movements as $m) {
            $item = [
                "hora" => $m->getCreatedAt()->format('H:i'),
                "monto" => number_format((float)$m->getAmount(), 2, '.', ''),
                "motivo" => $m->getDescription(),
                "usuario" => trim($user->getName() . ' ' . $user->getLastName())
            ];

            if ($m->getType()->value === CashMovementType::INCOME->value) {
                $ingresos[] = $item;
            } else {
                $retiros[] = $item;
            }
        }

        // 2. Clasificar Ventas por Tipo de Pago (Desde el reporte X)
        $ventasEfectivo = 0.00;
        $ventasTarjeta = 0.00;
        $ventasTransferencia = 0.00;

        foreach ($report->getDetails() as $detail) {
            $name = strtolower($detail->getPaymentType()->getName());
            $amount = (float)$detail->getSystemAmount();

            if (str_contains($name, 'efectivo')) $ventasEfectivo += $amount;
            elseif (str_contains($name, 'tarjeta')) $ventasTarjeta += $amount;
            elseif (str_contains($name, 'transferencia')) $ventasTransferencia += $amount;
        }

        // 3. Lógica de Propinas (Tips) - Navegando por Ventas de la Sesión
        $propinaEfectivo = 0.00;
        $propinaTarjeta = 0.00;

        // Buscamos todas las ventas asociadas a la sesión
        $sales = $saleRepo->findBy(['cashBox' => $session->getCashBox()]);

        foreach ($sales as $sale) {
            // 1. Recorremos los pagos de la venta para buscar propinas
            foreach ($sale->getPayments() as $payment) {

                // Buscamos las propinas asociadas a este pago específico
                $tips = $tipRepo->findBy(['salePayment' => $payment->getId()]);

                foreach ($tips as $tip) {
                    $tipAmount = (float)$tip->getAmount();

                    /**
                     * REGLA DE NEGOCIO:
                     * Aunque la propina esté vinculada a este $payment, el "tipo" de la propina
                     * debe determinarse inspeccionando TODOS los pagos de la venta ($sale).
                     */
                    $finalType = null;

                    foreach ($sale->getPayments() as $sPayment) {
                        $name = strtolower($sPayment->getPaymentType()->getName());

                        if (str_contains($name, 'efectivo')) {
                            $finalType = 'efectivo';
                            break; // Prioridad total: si hubo efectivo en la venta, la propina va a efectivo
                        }

                        if (str_contains($name, 'tarjeta')) {
                            $finalType = 'tarjeta';
                        }
                    }

                    // 2. Sumamos según el tipo encontrado en la venta
                    if ($finalType === 'efectivo') {
                        $propinaEfectivo += $tipAmount;
                    } elseif ($finalType === 'tarjeta') {
                        $propinaTarjeta += $tipAmount;
                    }
                }
            }
        }

        // 4. Determinar Estatus
        $diff = (float)$report->getDifference();
        $estatus = "CUADRADO";
        if ($diff > 0.01) $estatus = "SOBRANTE";
        if ($diff < -0.01) $estatus = "FALTANTE";

        $response = [
            [
                "templateId" => 20,
                "printerName" => $cashBox->getName(),
                "data" => [
                    "negocio" => [
                        "nombreComercial" => $company->getName()
                    ],
                    "corte" => [
                        "caja" => $cashBox->getName(),
                        "cajero" => trim($user->getName() . ' ' . $user->getLastName()),
                        "periodo" => [
                            "inicio" => $session->getOpeningDate()->format('H:i'),
                            "fin" => $now->format('H:i')
                        ],
                        "fondoInicial" => number_format((float)$session->getInitialAmount(), 2, '.', ''),
                        "ventas" => [
                            "efectivo" => number_format($ventasEfectivo, 2, '.', ''),
                            "tarjeta" => number_format($ventasTarjeta, 2, '.', ''),
                            "transferencias" => number_format($ventasTransferencia, 2, '.', ''),
                            "cortesias" => "0.00"
                        ],
                        "ingresos" => $ingresos,
                        "retiros" => $retiros,
                        "efectivo" => [
                            "declarado" => number_format((float)$report->getDeclaredTotal(), 2, '.', '')
                        ],
                        "propinas" => [
                            "efectivo" => number_format($propinaEfectivo, 2, '.', ''),
                            "tarjeta" => number_format($propinaTarjeta, 2, '.', '')
                        ],
                        "estatus" => $estatus,
                        "impresoAt" => $now->format('d/m/Y H:i:s'),
                        "firmas" => [
                            "cajero" => [
                                "nombre" => trim($user->getName() . ' ' . $user->getLastName()),
                                "fecha" => $now->format('d/m/Y')
                            ],
                            "supervisor" => [
                                "nombre" => trim($user->getName() . ' ' . $user->getLastName()),
                                "fecha" => $now->format('d/m/Y')
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return json_encode($response);
    }
}
