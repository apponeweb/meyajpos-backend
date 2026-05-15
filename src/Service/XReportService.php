<?php

namespace App\Service;

use App\Entity\CashBoxMovement;
use App\Entity\CashBoxSession;
use App\Entity\Company;
use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\SaleDetail;
use App\Entity\Tip;
use App\Entity\User;
use App\Entity\XReport;
use App\Enum\CashBoxSessionStatus;
use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use App\Enum\PaymentTypeEnum;
use App\Repository\CashBoxMovementRepository;
use App\Repository\CashBoxSessionRepository;
use App\Repository\SalePaymentRepository;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;


class XReportService
{
    public function __construct(
        private readonly CashBoxSessionRepository $sessionRepo,
        private readonly CashBoxMovementRepository $movementRepo,
        private readonly SaleRepository $saleRepo,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security

    ) {
    }

    public function getPreviewData($mode = null, ?int $activeBranchId = null): array
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new \Exception('Usuario no autenticado', 401);
        }

        $session = $this->sessionRepo->findOneBy(['user' => $user, 'status' => CashBoxSessionStatus::OPEN]);

        if (!$session) {
            throw new \Exception('No tiene una sesión de la caja activa', 404);
        }

        if ($activeBranchId && $session->getCashBox()->getBranch()->getId() !== $activeBranchId) {
            throw new \Exception('La sesión activa no corresponde a la sucursal seleccionada', 409);
        }

        $salesCount = $this->saleRepo->count(['cashBoxSession' => $session->getId()]);
        if ($salesCount === 0 && $mode == null) {
            //throw new \Exception('No se puede generar un corte X por no haber ventas realizadas en esta sesión.', 400);
        }

        $allPaymentTypes = $this->entityManager->getRepository(PaymentType::class)->findBy(['isActive' => true]);

        $previewDetails = [];
        $totalSystem = '0.00';

        foreach ($allPaymentTypes as $paymentType) {
            $amount = $this->calculateSystemAmount($session, $paymentType);

            $previewDetails[] = [
                'payment_type_id' => $paymentType->getId(),
                'payment_type_name' => $paymentType->getName(),
                'is_cash' => $paymentType->isCash(),
                'system_amount' => $amount,
            ];

            $totalSystem = bcadd($totalSystem, $amount, 2);
        }

        return [
            'session_id' => $session->getId(),
            'initial_amount' => number_format($session->getInitialAmount(), 2, '.', ','),
            'system_total' => number_format($totalSystem, 2, '.', ','),
            'total_deposits' => number_format($this->movementRepo->getTotalDeposits($session), 2, '.', ','),
            'total_extractions' => number_format($this->movementRepo->getTotalWithdrawals($session), 2, '.', ','),
            'details' => $previewDetails
        ];
    }

    /**
     * Centralizamos el cálculo aquí (o muévelo al SaleRepository para mejor rendimiento)
     */
    public function calculateSystemAmount(CashBoxSession $session, PaymentType $paymentType): string
    {
        // Obtenemos el total de ventas para este tipo de pago
        $amount = $this->saleRepo->getSummaryByPaymentType($session, $paymentType->getId());

        // Si es efectivo, ajustamos con saldo inicial y movimientos (Depósitos/Retiros)
        if ($paymentType->isCash()) {
            // (Ventas + Inicial + Depósitos)
            $amount = bcadd($amount, $session->getInitialAmount(), 2);
            $amount = bcadd($amount, $this->movementRepo->getTotalDeposits($session), 2);

            // - Retiros
            $amount = bcsub($amount, $this->movementRepo->getTotalWithdrawals($session), 2);
        }

        return $amount;
    }

    public function generateTicketData(XReport $report)
    {
        $session = $report->getCashSession();
        $user = $this->entityManager->getRepository(User::class)->find($report->getUser()->getId());

        $cashBox = $session->getCashBox();
        $company = $cashBox->getBranch()->getCompany();
        $movementRepo = $this->entityManager->getRepository(CashBoxMovement::class);
        $tipRepo = $this->entityManager->getRepository(Tip::class);
        $saleRepo = $this->entityManager->getRepository(Sale::class);
        $now = new \DateTime();

        // Función auxiliar para formatear moneda localmente
        $fmt = fn($n) => '$' . number_format((float) $n, 2, '.', ',');


        // 1. Clasificar Movimientos (Ingresos y Retiros)
        $movements = $movementRepo->findBy(['cashBoxSession' => $session]);
        $ingresosItems = [];
        $retirosItems = [];
        $totalIngresosVal = 0;
        $totalRetirosVal = 0;

        foreach ($movements as $m) {
            $amount = (float) $m->getAmount();
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

        $sales = $saleRepo->findBy(['cashBoxSession' => $session->getId()]);
        /** @var Sale $sale */
        foreach ($sales as $sale) {
            $change = (float) $sale->getChange();
            foreach ($sale->getPayments() as $payment) {
                $id = strtolower($payment->getPaymentType()->getId());


                $amount = (float) $payment->getAmountReceived();
                if ($id == PaymentTypeEnum::CASH->value)
                    $ventasEfectivo += $amount;
                elseif ($id == PaymentTypeEnum::CARD->value)
                    $ventasTarjeta += $amount;
                elseif ($id == PaymentTypeEnum::TRANSFER->value)
                    $ventasTransferencia += $amount;
            }
            $ventasEfectivo = $ventasEfectivo - $change;
        }


        // 3. Lógica de Propinas (Tips)
        $propinaEfectivo = 0.00;
        $propinaTarjeta = 0.00;
        $propinaTransferencia = 0.00;


        $ventasEfectivoV2 = 0.00; // sumar todo lo pagado en las ventas que sea efectivo
        $ventasTarjetaV2 = 0.00;
        $ventasTransferenciaV2 = 0.00;
        $courtesy = 0.00;

        $changeSale = 0;
        foreach ($sales as $sale) {
            $changeSale += $sale->getChange();
            foreach ($sale->getPayments() as $payment) {
                $tips = $tipRepo->findBy(['salePayment' => $payment->getId()]);
                $id = $payment->getPaymentType()->getId();

                foreach ($tips as $tip) {
                    $tipAmount = (float) $tip->getAmount();
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
                    if ($finalType === 'efectivo')
                        $propinaEfectivo += $tipAmount;
                    elseif ($finalType === 'tarjeta')
                        $propinaTarjeta += $tipAmount;
                    elseif ($finalType === 'transferencia')
                        $propinaTransferencia += $tipAmount;
                }
                $amount = (float) $payment->getAmountReceived();

                if ($id == PaymentTypeEnum::CASH->value)
                    $ventasEfectivoV2 += $amount;
                elseif ($id == PaymentTypeEnum::CARD->value)
                    $ventasTarjetaV2 += $amount;
                elseif ($id == PaymentTypeEnum::TRANSFER->value)
                    $ventasTransferenciaV2 += $amount;
            }
            /** @var SaleDetail $detail */
            foreach ($sale->getDetails() as $detail) {
                if ($detail->getProduct()->getServiceType()->isCourtesy()) {
                    $courtesy += $detail->getProduct()->getPrice();
                }
            }
        }


        $ventasEfectivoV2 -= $propinaEfectivo;
        $ventasEfectivoV2 -= $changeSale;
        $ventasTarjetaV2 -= $propinaTarjeta;


        // 4. Lógica de Efectivo en Caja (Usamos la data guardada en el reporte)
        $efectivoEsperado = 0.00;
        $efectivoDeclarado = 0.00;
        $diferencia = 0.00;

        foreach ($report->getDetails() as $detail) {
            if ($detail->getPaymentType()->isCash()) {
                $efectivoEsperado = (float) $detail->getSystemAmount();
                $efectivoDeclarado = (float) $detail->getDeclaredAmount();
                $diferencia = (float) $detail->getSystemAmount() - (float) $detail->getDeclaredAmount();
                break;
            }
        }

        // 5. Determinar Estatus basado en la diferencia (Sistema - Declarado)
        $estatus = "CUADRADO";
        if ($diferencia > 0.01) {
            $estatus = "FALTANTE";
        } elseif ($diferencia < -0.01) {
            $estatus = "SOBRANTE";
        }

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
                        "efectivo" => $fmt($ventasEfectivoV2),
                        "tarjeta" => $fmt($ventasTarjetaV2),
                        "transferencias" => $fmt($ventasTransferenciaV2),
                        "cortesias" => $fmt($courtesy),
                        "totalVentas" => $fmt($ventasEfectivoV2 + $ventasTarjetaV2 + $ventasTransferenciaV2 + $courtesy)
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
