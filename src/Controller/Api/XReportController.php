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
                'message' => "No se puede generar un corte X por no haber ventas realizadas en esta sesión.",
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
                'system_amount' => $amount,
            ];

            $totalSystem = bcadd($totalSystem, $amount, 2);
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'session_id' => $session->getId(),
                'initial_amount' => number_format($session->getInitialAmount(), 2, '.', ','),
                'system_total' => number_format($totalSystem, 2, '.', ','),
                'total_deposits' => number_format($movementRepo->getTotalDeposits($session), 2, '.', ','),
                'total_extractions' => number_format($movementRepo->getTotalWithdrawals($session), 2, '.', ','),
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
//        $report = $reportRepo->find(51);
//        $session = $cashBoxSessionRepository->find(5);
//        $ticket = $this->generateXTicketData($report, $session, $user, $em);
////
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
                    'system_total' => number_format((float)$report->getSystemTotal(), 2, '.', ','),
                    'declared_total' => number_format((float)$report->getDeclaredTotal(), 2, '.', ','),
                    'difference' => number_format((float)$report->getDifference(), 2, '.', ','),
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

        // Función auxiliar para formatear moneda localmente
        $fmt = fn($n) => '$' . number_format((float)$n, 2, '.', ',');

        // 1. Clasificar Movimientos (Ingresos y Retiros)
        $movements = $movementRepo->findBy(['cashBoxSession' => $session]);
        $ingresosItems = [];
        $retirosItems = [];
        $totalIngresosVal = 0;
        $totalRetirosVal = 0;

        foreach ($movements as $m) {
            $amount = (float)$m->getAmount();
            $item = [
                "hora" => $m->getCreatedAt()->format('H:i'),
                "monto" => $fmt($amount),
                "usuario" => trim($user->getName()),
                "motivo" => $m->getDescription(),
                "ref" => null,
            ];

            if ($m->getType()->value === CashMovementType::INCOME->value) {
                if ($m->getConcept()->value !== CashMovementConcept::SALE->value && $m->getConcept()->value !== CashMovementConcept::OPEN_CASH_BOX->value) {
                    $ingresosItems[] = $item;
                    $totalIngresosVal += $amount;
                }
            } else {
                $retirosItems[] = $item;
                $totalRetirosVal += $amount;
            }
        }

        // 2. Clasificar Ventas por Tipo de Pago
        $ventasEfectivo = 0.00;
        $ventasTarjeta = 0.00;
        $ventasTransferencia = 0.00;

        $sales = $saleRepo->findBy(['cashBox' => $session->getCashBox(), 'cashBoxSession' => $session->getId()]);
        foreach ($sales as $sale) {
            foreach ($sale->getPayments() as $payment) {
                $name = strtolower($payment->getPaymentType()->getName());

                $amount = (float)$payment->getAmountReceived();
                if (str_contains($name, 'efectivo')) $ventasEfectivo += $amount;
                elseif (str_contains($name, 'tarjeta')) $ventasTarjeta += $amount;
                elseif (str_contains($name, 'transferencia')) $ventasTransferencia += $amount;
            }
        }


        // 3. Lógica de Propinas (Tips)
        $propinaEfectivo = 0.00;
        $propinaTarjeta = 0.00;
        $propinaTransferencia = 0.00;


        $ventasEfectivoV2 = 0.00; // sumar todo lo pagado en las ventas que sea efectivo
        $ventasTarjetaV2 = 0.00;
        $ventasTransferenciaV2 = 0.00;
        $courtesy = 0.00;

        foreach ($sales as $sale) {
            foreach ($sale->getPayments() as $payment) {
                $tips = $tipRepo->findBy(['salePayment' => $payment->getId()]);
                $name = strtolower($payment->getPaymentType()->getName());
                foreach ($tips as $tip) {
                    $tipAmount = (float)$tip->getAmount();
                    $finalType = null;
                    foreach ($sale->getPayments() as $sPayment) {
                        $name = strtolower($sPayment->getPaymentType()->getName());
                        if (str_contains($name, 'efectivo')) {
                            $finalType = 'efectivo';
                            break;
                        }
                        if (str_contains($name, 'tarjeta')) {
                            $finalType = 'tarjeta';
                        }
                    }
                    if ($finalType === 'efectivo') $propinaEfectivo += $tipAmount;
                    elseif ($finalType === 'tarjeta') $propinaTarjeta += $tipAmount;
                    elseif ($finalType === 'transferencia') $propinaTransferencia += $tipAmount;
                }
                $amount = (float)$payment->getAmountReceived();
                if (str_contains($name, 'efectivo')) $ventasEfectivoV2 += $amount;
                elseif (str_contains($name, 'tarjeta')) $ventasTarjetaV2 += $amount;
                elseif (str_contains($name, 'transferencia')) $ventasTransferenciaV2 += $amount;
            }
            /** @var SaleDetail $detail */
            foreach ($sale->getDetails() as $detail) {
                if ($detail->getProduct()->getServiceType()->isCourtesy()) {
                    $courtesy += $detail->getProduct()->getPrice();
                }
            }
        }
        $ventasEfectivoV2 -= $propinaEfectivo;
        $ventasTarjetaV2 -= $propinaTarjeta;


        // 4. Lógica de Efectivo en Caja
        // Efectivo Esperado = Fondo Inicial + Ventas Efectivo + Ingresos - Retiros
        $efectivoEsperado = (float)$session->getInitialAmount() + $ventasEfectivoV2 + $totalIngresosVal - $totalRetirosVal + $propinaEfectivo;
        $efectivoDeclarado = 0.00;
        foreach ($report->getDetails() as $detail) {
            if ($detail->getPaymentType()->isCash()) {
                $efectivoDeclarado = (float)$detail->getDeclaredAmount();
                break;
            }
        }
        $diferencia = $efectivoDeclarado - $efectivoEsperado;

        // 5. Determinar Estatus
        $estatus = "CUADRADO";
        if ($diferencia > 0.01) $estatus = "SOBRANTE";
        if ($diferencia < -0.01) $estatus = "FALTANTE";

        $response = [
            "templateId" => 20,
            "printerName" => $cashBox->getName() . " - " . $cashBox->getBranch()->getName(),
            "data" => [
                "negocio" => [
                    "nombreComercial" => $company->getName()
                ],
                "corte" => [
                    "caja" => $cashBox->getName() . " - " . $cashBox->getBranch()->getName(),
                    "cajero" => trim($user->getName()),
                    "periodo" => [
                        "inicio" => $session->getOpeningDate()->format('H:i'),
                        "fin" => $now->format('H:i')
                    ],
                    "fondoInicial" => $fmt($session->getInitialAmount()),
                    "ventasPorFormaPago" => [
                        "efectivo" => $fmt($ventasEfectivo - $propinaEfectivo),
                        "tarjeta" => $fmt($ventasTarjeta - $propinaTarjeta),
                        "transferencias" => $fmt($ventasTransferencia - $propinaTransferencia),
                        "cortesias" => $fmt($courtesy),
                        "totalVentas" => $fmt(($ventasEfectivo - $propinaEfectivo) + ($ventasTarjeta - $propinaTarjeta) + ($ventasTransferencia - $propinaTransferencia) + $courtesy)
                    ],
                    "ingresosCaja" => [
                        "movimientos" => $ingresosItems,
                        "totalIngresos" => $fmt($totalIngresosVal)
                    ],
                    "retirosCaja" => [
                        "movimientos" => $retirosItems,
                        "totalRetiros" => $fmt($totalRetirosVal)
                    ],
                    "efectivoEnCaja" => [
                        "efectivoEnCaja" => $fmt($efectivoEsperado), // Valor calculado en sistema
                        "efectivoEsperado" => $fmt($efectivoEsperado),
                        "efectivoDeclarado" => $fmt($efectivoDeclarado),
                        "diferencia" => ($diferencia >= 0 ? '' : '-') . $fmt(abs($diferencia))
                    ],
                    "propinas" => [
                        "efectivo" => $fmt($propinaEfectivo),
                        "tarjeta" => $fmt($propinaTarjeta),
                        "transferencia" => $fmt($propinaTransferencia),
                        "totalPropinas" => $fmt($propinaEfectivo + $propinaTarjeta + $propinaTransferencia)
                    ],
                    "estatus" => $estatus,
                    "impresoAt" => $now->format('Y-m-d H:i'),
                    "firmas" => [
                        "cajero" => [
                            "nombre" => trim($user->getName()),
                            "fecha" => $now->format('Y-m-d')
                        ],
                        "supervisor" => [
                            "nombre" => "Supervisor", // Puedes ajustar según lógica de quién autoriza
                            "fecha" => $now->format('Y-m-d')
                        ]
                    ]
                ]
            ]
        ];

        return json_encode([$response]);
    }
}
