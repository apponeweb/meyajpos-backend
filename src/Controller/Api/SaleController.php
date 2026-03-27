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
use App\Service\SaleService;
use App\Service\XReportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use phpDocumentor\Reflection\Types\This;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    public function create(Request $request, XReportService $reportService, CashBoxSessionRepository $sessionRepo, CashBoxMovementService $cashBoxMovementService, SaleService $saleService): JsonResponse
    {
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

        $today = new \DateTime();
        if ($activeSession->getCreatedAt()->format('Y-m-d') !== $today->format('Y-m-d')) {
            return $this->json([
                'message' => 'No puedes realizar una nueva venta hasta que hagas el cierre del día anterior.',
                'errors' => [
                    'cashBox' => 'No puedes realizar una nueva venta hasta que hagas el cierre del día anterior.'
                ]
            ], JsonResponse::HTTP_BAD_REQUEST);
        }


        $sale = new Sale();

        $now = new \DateTime();
        // Asignamos datos automáticos antes de procesar el formulario
        $sale->setUser($user);
        $sale->setCashBox($activeSession->getCashBox());
        $sale->setCashBoxSession($activeSession);
        $sale->setSaleDate($now);
        $newFolio = $this->generateDailyFolio($now, $activeSession->getCashBox());
        $sale->setFolio($newFolio);
        $sale->setCreatedAt($now);
        $sale->setUpdatedAt($now);
        $sale->setCreatedBy($user->getId());
        $sale->setUpdatedBy($user->getId());

        // 2. Procesar Formulario
        $form = $this->createForm($this->getFormTypeClass(), $sale);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isSubmitted() && $form->isValid()) {


            $previewData = $reportService->getPreviewData('sale')['details'];
            $efective = 0;
            foreach ($previewData as $value) {
                if ($value['is_cash']) {
                    $efective = $value['system_amount'];
                    break;
                }
            }
            if ($sale->getChange() > $efective) {
                return $this->json([
                    'message' => "El cambio a entregar debe ser menor que el efectivo en caja ($efective)",
                    'status' => JsonResponse::HTTP_BAD_REQUEST
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            $saleDetails = $sale->getDetails();
            /** @var SaleDetail $detail */
            foreach ($saleDetails ?? [] as $detail) {
                if ($detail->getServiceProvider()->getBarberSn()) {
                    $commision = $detail->getServiceProvider()->getCommission()->getId();
                    $productType = $detail->getProduct()->getServiceType()->getId();
                    $commisionDetail = $this->entityManager->getRepository(CommissionDetail::class)->findOneBy(['commission' => $commision, 'serviceType' => $productType]);
                    if ($commisionDetail) {
                        $percentage = $commisionDetail->getPercentage();
                        $commissionAmount = ($detail->getProduct()->getPrice() * $percentage) / 100;

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

            $data = json_decode($request->getContent(), true);
            if (empty($data['payments'])) {
                $saleService->initializeEmptyPayments($sale, $user);
            }

            $firstPayment = $sale->getPayments()->first();

            // 1. Procesar Pagos y sus campos de auditoría
            foreach ($sale->getPayments() as $payment) {
                $payment->setCreatedBy($user->getId());
                $payment->setUpdatedBy($user->getId());
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
            $this->entityManager->flush();

            return $this->json([
                'message' => "Venta registrada correctamente",
                'data' => [
                    'id' => $sale->getId(),
                    'folio' => $sale->getFolio(),
                    'ticket' => $saleService->generateTicketData($sale)
                ]
            ], JsonResponse::HTTP_OK);
        }

        // 3. Captura de errores detallada (Aquí resolvemos el JSON vacío)
        return $this->json([
            'message' => 'Validación fallida',
            'errors' => $this->getFormErrorsAsArray($form)
        ], JsonResponse::HTTP_BAD_REQUEST);
    }


    private function generateDailyFolio(\DateTime $date, \App\Entity\CashBox $cashBox): string
    {
        $repository = $this->entityManager->getRepository(Sale::class);

        // Prefijo del día actual y ID de caja para buscar el máximo consecutivo
        $datePrefix = 'V-' . $date->format('Ymd') . '-' . $cashBox->getId() . '-';

        // Obtener el folio máximo del día actual PARA ESTA CAJA
        $maxFolio = $repository->createQueryBuilder('s')
            ->select('MAX(s.folio)')
            ->where('s.folio LIKE :prefix')
            ->andWhere('s.cashBox = :cashBox')
            ->setParameter('prefix', $datePrefix . '%')
            ->setParameter('cashBox', $cashBox)
            ->getQuery()
            ->getSingleScalarResult();

        if ($maxFolio) {
            // Extraer el consecutivo del folio máximo (últimos 4 dígitos después del último guion)
            $parts = explode('-', $maxFolio);
            $lastNumber = (int)end($parts);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Formato: V-AÑO-MES-DIA-IDCAJA-CONSECUTIVO (con ceros a la izquierda)
        // Ejemplo: V-20260126-1-0001
        return sprintf('%s%04d', $datePrefix, $nextNumber);
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
    public function get(int $id, SaleRepository $repository): JsonResponse
    {
        $sale = $repository->find($id);
        if (!$sale) {
            return $this->json(['message' => 'Venta no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $sale->getId(),
            'folio' => $sale->getFolio(),
            'saleDate' => $sale->getSaleDate()?->format('d/m/Y H:i:s'),
            'createdAt' => $sale->getCreatedAt()?->format('d/m/Y H:i:s'),
            'subtotal' => $this->moneyFormat($sale->getSubtotal()),
            'totalTax' => $this->moneyFormat($sale->getTotalTax()),
            'total' => $this->moneyFormat($sale->getTotal()),
            'change' => $this->moneyFormat($sale->getChange()),
            'status' => $sale->getStatus()->value,
            'user' => $sale->getUser() ? [
                'id' => $sale->getUser()->getId(),
                'name' => $sale->getUser()->getName(),
                'lastName' => $sale->getUser()->getLastName(),
            ] : null,
            'cashBox' => $sale->getCashBox() ? [
                'id' => $sale->getCashBox()->getId(),
                'name' => $sale->getCashBox()->getName(),
            ] : null,
            'details' => [],
            'payments' => []
        ];

        foreach ($sale->getDetails() as $detail) {
            $data['details'][] = [
                'id' => $detail->getId(),
                'itemLine' => $detail->getItemLine(),
                'quantity' => $detail->getQuantity(),
                'unitPrice' => $this->moneyFormat($detail->getUnitPrice()),
                'subtotal' => $this->moneyFormat($detail->getSubtotal()),
                'tax' => $this->moneyFormat($detail->getTax()),
                'total' => $this->moneyFormat($detail->getTotal()),
                'isCourtesy' => $detail->isCourtesy(),
                'product' => $detail->getProduct() ? [
                    'id' => $detail->getProduct()->getId(),
                    'name' => $detail->getProduct()->getName(),
                ] : null,
                'serviceProvider' => $detail->getServiceProvider() ? [
                    'id' => $detail->getServiceProvider()->getId(),
                    'name' => $detail->getServiceProvider()->getName(),
                    'lastName' => $detail->getServiceProvider()->getLastName(),
                ] : null,
                'tip' => $this->moneyFormat((float)$detail->getTotal() - (float)$detail->getUnitPrice())
            ];
        }

        foreach ($sale->getPayments() as $payment) {
            $data['payments'][] = [
                'id' => $payment->getId(),
                'amountReceived' => $this->moneyFormat($payment->getAmountReceived()),
                'amountLocalCurrency' => $this->moneyFormat($payment->getAmountLocalCurrency()),
                'exchangeRateUsed' => number_format((float)($payment->getExchangeRateUsed() ?? 1), 2, '.', ','),
                'paymentType' => $payment->getPaymentType() ? [
                    'id' => $payment->getPaymentType()->getId(),
                    'name' => $payment->getPaymentType()->getName(),
                ] : null,
                'currency' => $payment->getCurrency() ? [
                    'id' => $payment->getCurrency()->getId(),
                    'name' => $payment->getCurrency()->getName(),
                ] : null,
            ];
        }

        return $this->json($data, Response::HTTP_OK);
    }

    /**
     * Función privada para no repetir código
     */
    private function moneyFormat($value): string
    {
        return number_format((float)$value, 2, '.', ',');
    }

    #[Rest\Delete('/sale/{id}')]
    public function deleteSale(?Sale $sale, EntityManagerInterface $em): JsonResponse
    {
        if (!$sale) {
            return $this->json([
                'message' => 'La venta con el ID proporcionado no existe o ya fue eliminada',
                'status' => Response::HTTP_NOT_FOUND
            ], Response::HTTP_NOT_FOUND);
        }
        try {
            $em->remove($sale);
            $id = $sale->getId();
            $ticket = $sale->getFolio();
            $em->flush();

            return $this->json([
                'status' => Response::HTTP_OK,
                'message' => "La venta #{$id} y ticket:{$ticket} ha sido eliminada correctamente."
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'No se pudo completar la eliminación',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
