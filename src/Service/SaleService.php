<?php

namespace App\Service;

use App\Entity\CashBoxMovement;
use App\Entity\Company;
use App\Entity\Currency;
use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\SaleDetail;
use App\Entity\SalePayment;
use App\Entity\User;
use App\Enum\CashBoxSessionStatus;
use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use App\Enum\PaymentTypeEnum;
use App\Repository\CashBoxMovementRepository;
use App\Repository\CashBoxSessionRepository;
use App\Repository\SalePaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class SaleService
{
    public function __construct(
        private EntityManagerInterface $em,
    )
    {
    }

    public function generateTicketData(Sale $sale)
    {
        $cashBox = $sale->getCashBox();
        /** @var Company $company */
        $company = $cashBox->getBranch()->getCompany();

        // 1. Obtener nombres únicos de los barberos
        $barberos = [];
        foreach ($sale->getDetails() as $detail) {
            $name = trim($detail->getServiceProvider()->getName() . ' ' . $detail->getServiceProvider()->getLastName());
            if (!in_array($name, $barberos)) {
                $barberos[] = $name;
            }
        }

        // 2. Mapear items del detalle con formateo numérico
        $items = [];
        $totalVat = 0;
        /** @var SaleDetail $detail */
        foreach ($sale->getDetails() as $detail) {
            $product = $detail->getProduct();
            $unitPrice = (float)$detail->getUnitPrice();
            $total = (float)$detail->getTotal();

            $totalVat += (float)$product->getVatRate();

            $items[] = [
                "descripcion" => $product->getName(),
                "cantidad" => (int)$detail->getQuantity(),
                "precio" => number_format($unitPrice, 2, '.', ''), // Formato 0.00
                "importe" => number_format($total, 2, '.', '')     // Formato 0.00
            ];
        }

        // 3. Totales por método de pago con formateo
        $pagosMap = ["Tarjeta" => 0.0, "Efectivo" => 0.0, "Transferencia" => 0.0];
        foreach ($sale->getPayments() as $payment) {
            $typeName = $payment->getPaymentType()->getName();
            $amount = (float)$payment->getAmountReceived();

            if (str_contains(strtolower($typeName), 'tarjeta')) $pagosMap["Tarjeta"] += $amount;
            elseif (str_contains(strtolower($typeName), 'efectivo')) $pagosMap["Efectivo"] += $amount;
            elseif (str_contains(strtolower($typeName), 'transferencia')) $pagosMap["Transferencia"] += $amount;
        }


        $rawAddress = '';
        try {
            // Usamos un getter seguro o verificamos la inicialización
            $rawAddress = $company->getTaxAddress() ?? '';
        } catch (\Error $e) {
            // Si falla por no estar inicializada, queda como string vacío
            $rawAddress = '';
        }
        $formattedAddress = $rawAddress; // fallback
        $addressData = null;
        if (!empty($rawAddress)) {
            $addressData = json_decode($rawAddress, true);
        }

        if (json_last_error() === JSON_ERROR_NONE && is_array($addressData)) {
            $formattedAddress = sprintf(
                "%s %s, Col. %s, %s, %s. CP: %s",
                $addressData['street'] ?? '',
                $addressData['streetNumber'] ?? '',
                $addressData['neighborhood'] ?? '',
                $addressData['city'] ?? '',
                $addressData['state'] ?? '',
                $addressData['postalCode'] ?? ''
            );
        }

        $final = [
            [
                "templateId" => 10,
                "printerName" => $cashBox->getName(),
                "data" => [
                    "negocio" => [
                        "nombreComercial" => $company->getName(),
                        "razonSocial" => $company->getLegalName(),
                        "direccion" => $formattedAddress,
                        "telefono" => $company->getPhone(),
                        "rfc" => $company->getRfc()
                    ],
                    "transaccion" => [
                        "folio" => $sale->getFolio(),
                        "fechaHora" => $sale->getSaleDate()->format('d/m/Y H:i:s'),
                        "barbero" => implode(", ", $barberos)
                    ],
                    "detalle" => [
                        "items" => $items
                    ],
                    "totales" => [
                        "subtotal" => number_format((float)$sale->getSubtotal(), 2, '.', ''),
                        "iva" => number_format($totalVat, 2, '.', ''),
                        "total" => number_format((float)$sale->getSubtotal(), 2, '.', ''),
                        "Tarjeta" => number_format($pagosMap["Tarjeta"], 2, '.', ''),
                        "Efectivo" => number_format($pagosMap["Efectivo"], 2, '.', ''),
                        "Transferencia" => number_format($pagosMap["Transferencia"], 2, '.', ''),
                        "Propina" => ($sale->getTotal() - $sale->getSubtotal())
                    ],
                    "adicional" => [
                        "politicas" => "Cancelaciones con 2 horas de anticipación. Reagendos sujetos a disponibilidad.",
                        "redes" => "IG: @eltiosbarber | www.eltiosbarber.com"
                    ]
                ]
            ]
        ];
        return json_encode($final);
    }


    public function initializeEmptyPayments(Sale $sale, User $user): void
    {
        $isCourtesy = false;
        /** @var SaleDetail $value */
        foreach ($sale->getDetails() as $value) {
            if ($value->getProduct()->getServiceType()->isCourtesy()) {
                $isCourtesy = true;
                break;
            }
        }

        if ($isCourtesy) {
            $payment = new SalePayment();
            $payment->setSale($sale);
            $payment->setPaymentType($this->em->getRepository(PaymentType::class)->find(PaymentTypeEnum::CASH->value));
            $payment->setCurrency($this->em->getRepository(Currency::class)->find(1));
            $payment->setAmountReceived(0.00);
            $payment->setAmountLocalCurrency(0.00);
            $payment->setExchangeRateUsed(1.00);

            // Auditoría
            $payment->setCreatedBy($user->getId());
            $payment->setUpdatedBy($user->getId());

            $sale->addPayment($payment);
        }
    }
}
