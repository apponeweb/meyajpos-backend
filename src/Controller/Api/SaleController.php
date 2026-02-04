<?php

namespace App\Controller\Api;

use App\Entity\CashBoxMovement;
use App\Entity\CommissionDetail;
use App\Entity\CommissionGenerated;
use App\Entity\Company;
use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\SaleDetail;
use App\Entity\SalePayment;
use App\Entity\Tip;
use App\Enum\CashBoxSessionStatus;
use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use App\Form\Type\SaleFormType;
use App\Repository\CashBoxSessionRepository;
use App\Repository\SaleRepository;
use App\Service\CashBoxMovementService;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use phpDocumentor\Reflection\Types\This;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class SaleController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Sale::class;
    }

    protected function getFormTypeClass(): string
    {
        return SaleFormType::class;
    }

    /**
     * Configuramos los joins para obtener información de la Caja y el Usuario
     */
    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        // 'u' es la entidad Sale
        $qb->leftJoin('u.cashBox', 'cb')
            ->leftJoin('u.user', 'usr');

        // Filtro por Folio
        if ($folio = $request->query->get('folio')) {
            $qb->andWhere('u.folio LIKE :folio')
                ->setParameter('folio', '%' . $folio . '%');
        }

        // Filtro por Estado (Enum value)
        if ($status = $request->query->get('status')) {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', $status);
        }

        // Filtro por Rango de Fechas
        if ($startDate = $request->query->get('startDate')) {
            $qb->andWhere('u.dateTime >= :start')
                ->setParameter('start', new \DateTime($startDate));
        }
        if ($endDate = $request->query->get('endDate')) {
            $qb->andWhere('u.dateTime <= :end')
                ->setParameter('end', new \DateTime($endDate . ' 23:59:59'));
        }
    }

    /**
     * Definimos los campos de la venta, incluyendo el total y datos de relaciones
     */
    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.folio',
            'u.saleDate',
            'u.subtotal',
            'u.totalTax',
            'u.total',
            'u.status', // Doctrine serializará el valor prestablecido del Enum
            'cb.id as cashBoxId',
            'cb.name as cashBoxName',
            'usr.id as userId',
            'usr.name as userName'
        ];
    }

    #[Rest\Get('/sale')]
    public function index(Request $request, SaleRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/sale')]
    public function create(Request $request, CashBoxSessionRepository $sessionRepo, CashBoxMovementService $cashBoxMovementService): JsonResponse
    {
//        $sale = $this->entityManager->getRepository(Sale::class)->find(37);
//        echo '<pre>';
//        print_r($this->generateTicketData($sale));
//        die;
        $user = $this->security->getUser();

        // 1. Validar sesión abierta
        $activeSession = $sessionRepo->findOneBy([
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ]);

        if (!$activeSession) {
            return $this->json([
                'message' => 'Error de Caja',
                'errors' => ['cashBox' => 'No puedes registrar ventas sin una sesión de caja abierta.']
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $sale = new Sale();
        $now = new \DateTime();
        // Asignamos datos automáticos antes de procesar el formulario
        $sale->setUser($user);
        $sale->setCashBox($activeSession->getCashBox());
        $sale->setSaleDate($now);
        $newFolio = $this->generateDailyFolio($now);
        $sale->setFolio($newFolio);
        $sale->setCreatedAt($now);
        $sale->setUpdatedAt($now);
        $sale->setCreatedBy($user->getId());
        $sale->setUpdatedBy($user->getId());

        // 2. Procesar Formulario
        $form = $this->createForm($this->getFormTypeClass(), $sale);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isSubmitted() && $form->isValid()) {

            $saleDetails = $sale->getDetails();
            /** @var SaleDetail $detail */
            foreach ($saleDetails ?? [] as $detail) {
                if ($detail->getServiceProvider()->getBarberSn()) {
                    $commision = $detail->getServiceProvider()->getCommission()->getId();
                    $productType = $detail->getProduct()->getServiceType()->getId();
                    $commisionDetail = $this->entityManager->getRepository(CommissionDetail::class)->findOneBy(['commission' => $commision, 'serviceType' => $productType]);
                    if ($commisionDetail) {
                        $percentage = $commisionDetail->getPercentage();
                        $commissionAmount = ($detail->getTotal() * $percentage) / 100;

                        $commisionGenerated = new CommissionGenerated();
                        $commisionGenerated->setUser($detail->getServiceProvider());
                        $commisionGenerated->setSaleDetail($detail);
                        $commisionGenerated->setPercentage($percentage);
                        $commisionGenerated->setCommissionAmount($commissionAmount);
                        $commisionGenerated->setCreatedAt(new \DateTime());
                        $commisionGenerated->setUpdatedAt(new \DateTime());
                        $commisionGenerated->setCreatedBy($user->getId());
                        $commisionGenerated->setUpdatedBy($user->getId());
                        $this->entityManager->persist($commisionGenerated);
                    }
                }

            }

            $firstPayment = $sale->getPayments()->first();

            // 1. Procesar Pagos y sus campos de auditoría
            foreach ($sale->getPayments() as $payment) {
                $payment->setCreatedBy($user->getId()); // Suponiendo que BaseEntity tiene este método
                $payment->setUpdatedBy($user->getId()); // Suponiendo que BaseEntity tiene este método
            }

            // 2. Procesar Propinas, vincularlas a un pago y auditoría
            foreach ($sale->getTips() as $tip) {
                $tip->setCreatedBy($user->getId());
                $tip->setUpdatedBy($user->getId());
                $tip->setTipDate(new \DateTime());
                if ($firstPayment) {
                    $tip->setSalePayment($firstPayment);
                }
                $this->entityManager->persist($tip);
            }

            $this->entityManager->persist($sale);


            // Solo si hubo flujo de efectivo real (dinero a la gaveta)
            $totalCashReceived = $sale->getSubtotal();
            if ($totalCashReceived > 0) {
                $movement = new CashBoxMovement();
                $movement->setType(CashMovementType::INCOME);
                $movement->setConcept(CashMovementConcept::SALE);
                $movement->setAmount($totalCashReceived);
                $movement->setDescription("Ingreso automático por Venta Folio: " . $sale->getFolio());
                $movement->setChange($sale->getChange());

                $movementResult = $cashBoxMovementService->createMovement($movement);

                if (!$movementResult['success']) {
                    return $this->json($movementResult);
                }
            }


            $this->entityManager->flush();

            return $this->json([
                'message' => "Venta registrada correctamente",
                'data' => [
                    'id' => $sale->getId(),
                    'folio' => $sale->getFolio(),
                    'ticket' => $this->generateTicketData($sale)
                ]
            ], JsonResponse::HTTP_OK);
        }

        // 3. Captura de errores detallada (Aquí resolvemos el JSON vacío)
        return $this->json([
            'message' => 'Validación fallida',
            'errors' => $this->getFormErrorsAsArray($form)
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    private function generateTicketData(Sale $sale)
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

        // 4. Formatear Dirección JSON a Texto Lógico
        $rawAddress = $company->getTaxAddress();
        $formattedAddress = $rawAddress; // fallback

        $addressData = json_decode($rawAddress, true);
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
                        "telefono" =>  $company->getPhone(),
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
                        "subtotal" => number_format((float)$sale->getTotal(), 2, '.', ''),
                        "iva" => number_format($totalVat, 2, '.', ''),
                        "total" => number_format((float)$sale->getTotal(), 2, '.', ''),
                        "Tarjeta" => number_format($pagosMap["Tarjeta"], 2, '.', ''),
                        "Efectivo" => number_format($pagosMap["Efectivo"], 2, '.', ''),
                        "Transferencia" => number_format($pagosMap["Transferencia"], 2, '.', '')
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

    private function generateDailyFolio(\DateTime $date): string
    {
        $repository = $this->entityManager->getRepository(Sale::class);

        // Definir el rango del día (desde las 00:00:00 hasta las 23:59:59)
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        // Contar cuántas ventas se han realizado hoy
        $count = $repository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.saleDate BETWEEN :start AND :end')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        $nextNumber = (int)$count + 1;

        // Formato: AÑO-MES-DIA-CONSECUTIVO (con ceros a la izquierda)
        // Ejemplo: 20260126-0001
        return "V-" . sprintf('%s-%04d', $date->format('Ymd'), $nextNumber);
    }

    private function getFormErrorsAsArray($form): array
    {
        $errors = [];

        // Errores globales (Ej: La validación de suma de pagos en la Entidad)
        foreach ($form->getErrors() as $error) {
            $errors['global'][] = $error->getMessage();
        }

        // Errores en campos individuales
        foreach ($form->all() as $child) {
            if (!$child->isValid()) {
                $childErrors = $this->getFormErrorsAsArray($child);
                if (!empty($childErrors)) {
                    $errors['fields'][$child->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }

    #[Rest\Get('/sale/{id}')]
    public function get(Sale $id): JsonResponse
    {
        return $this->getDetails($id);
    }
}
