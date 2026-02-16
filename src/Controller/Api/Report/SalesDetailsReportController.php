<?php

namespace App\Controller\Api\Report;

use App\Repository\SaleDetailRepository;
use App\Repository\SalePaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;

#[Route('/reports')]
class SalesDetailsReportController extends AbstractController
{
    public function __construct(
        private readonly SaleDetailRepository  $detailRepository,
        private readonly SalePaymentRepository $salePaymentRepository,
    )
    {
    }

    #[Route('/sales-details', name: 'api_sales_details_report', methods: ['GET'])]
    public function getDetailsReport(Request $request): JsonResponse
    {
        try {
            $filters = [
                'startDate' => $request->query->get('startDate'),
                'endDate' => $request->query->get('endDate'),
                'barberId' => $request->query->get('barberId'),
                'serviceTypeId' => $request->query->get('serviceTypeId'),
                'search' => $request->query->get('search'),
            ];

            $current = $request->query->getInt('current', 1);
            $pageSize = $request->query->getInt('pageSize', 10);

            // Consultamos desde el repositorio de SalePayment
            $query = $this->salePaymentRepository->getDetailsReportQuery($filters);
            $query->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            // Ahora el Paginator no tendrá problemas con el ID porque sp.id es único
            $paginator = new Paginator($query, true);

            $results = [];
            foreach ($paginator as $sp) {
                /** @var \App\Entity\SalePayment $sp */
                $sale = $sp->getSale();
                $paymentMethodName = $sp->getPaymentType()->getName();

                $amountPaidWithThisMethod = (float)$sp->getAmountReceived();

                // Iteramos los detalles de la venta vinculada a este pago específico
                foreach ($sale->getDetails() as $sd) {
                    /** @var \App\Entity\SaleDetail $sd */
                    $product = $sd->getProduct();
                    $barber = $sd->getServiceProvider();

                    $unitPrice = (float)$sd->getUnitPrice();
                    $totalLine = (float)$sd->getTotal();
                    $quantity = (float)$sd->getQuantity();
                    $cashChange = (float)$sd->getSale()->getCashBox();
                    $tip = $totalLine - $unitPrice;
                    $results[] = [
                        // Generamos un ID único para el frontend (PagoID-DetalleID)
                        'id' => uniqid(),
                        'ticket' => $sale->getFolio(),
                        'servProd' => $product ? $product->getName() : 'N/A',
                        'serviceType' => $product?->getServiceType()?->getName() ?? 'N/A',
                        'barber' => $barber ? ($barber->getName() . ' ' . $barber->getLastName()) : 'Sin asignar',
                        'paymentMethod' => $paymentMethodName,
                        'paymentAmount' => number_format($amountPaidWithThisMethod, 2, '.', ','),
                        'quantity' => $quantity,
                        'unitPrice' => number_format($unitPrice, 2, '.', ','),
                        'total' => number_format($totalLine, 2, '.', ','),
                        'tip' => number_format($tip, 2, '.', ','),
                        'cashChange' => number_format($cashChange, 2, '.', ','),
                        'date' => $sale->getSaleDate()->format('d/m/Y H:i:s')
                    ];
                }
            }

            // Totales: Recuerda que la función de totales también debe salir de SalePayment
            // para que la duplicación de montos sea coherente con lo que se ve.
            $totals = $this->salePaymentRepository->getDetailsTotalAccumulated($filters);

            return $this->json([
                'total' => count($paginator), // Esto contará los pagos únicos encontrados
                'results' => $results,
                'current' => $current,
                'pageSize' => $pageSize,
                'summary' => [
                    'totalQuantity' => number_format($totals['sumQuantity'], 2),
                    'totalAmount' => number_format($totals['sumTotal'], 2, '.', ','),
                    'transfer' => number_format($totals['totalTransfer'], 2, '.', ','),
                    'card' => number_format($totals['totalCard'], 2, '.', ','),
                    'cash' => number_format($totals['totalCash'], 2, '.', ','),
                    'totalUnitPrice' => number_format($totals['sumUnitPrice'], 2, '.', ','),
                ],
                'status' => Response::HTTP_OK
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar detalle',
                'detail' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    #[Route('/sales-details/export', name: 'api_sales_details_export', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = [
            'startDate' => $request->query->get('startDate'),
            'endDate' => $request->query->get('endDate'),
            'barberId' => $request->query->get('barberId'),
            'serviceTypeId' => $request->query->get('serviceTypeId'),
            'search' => $request->query->get('search'),
        ];

        // 1. Usamos SalePaymentRepository para obtener los objetos completos sin paginar
        $payments = $this->salePaymentRepository->getDetailsReportQuery($filters)->getResult();

        $response = new StreamedResponse(function () use ($payments) {
            $handle = fopen('php://output', 'w+');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM para Excel UTF-8

            // Cabeceras
            fputcsv($handle, [
                'TICKET',
                'SERV/PROD',
                'TIPO',
                'BARBERO',
                'METODO PAGO',
                'CANTIDAD',
                'PRECIO U.',
                'TOTAL',
                'FECHA'
            ], ';');

            /** @var \App\Entity\SalePayment $sp */
            foreach ($payments as $sp) {
                $sale = $sp->getSale();
                $paymentMethodName = $sp->getPaymentType()->getName();
                $amountPaidWithThisMethod = (float)$sp->getAmountReceived();
                // Desglosamos detalles por cada pago (misma lógica que el JSON)
                foreach ($sale->getDetails() as $sd) {
                    /** @var \App\Entity\SaleDetail $sd */
                    $product = $sd->getProduct();
                    $barber = $sd->getServiceProvider();

                    $unitPrice = (float)$sd->getUnitPrice();
                    $totalLine = (float)$sd->getTotal();
                    $quantity = (float)$sd->getQuantity();

                    fputcsv($handle, [
                        $sale->getFolio(),
                        $product ? $product->getName() : 'N/A',
                        $product?->getServiceType()?->getName() ?? 'N/A',
                        $barber ? ($barber->getName() . ' ' . $barber->getLastName()) : 'Sin asignar',
                        $paymentMethodName,
                        number_format($amountPaidWithThisMethod, 2, '.', ','),
                        $quantity,
                        number_format($unitPrice, 2, '.', ''),
                        number_format($totalLine, 2, '.', ''),
                        $sale->getSaleDate()->format('d/m/Y H:i:s')
                    ], ';');
                }
            }
            fclose($handle);
        });

        $fileName = 'reporte_detalles_' . date('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}
