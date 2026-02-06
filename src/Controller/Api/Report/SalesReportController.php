<?php

namespace App\Controller\Api\Report;

use App\Enum\SaleStatus;
use App\Repository\SaleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;

#[Route('/reports')]
class SalesReportController extends AbstractController
{
    public function __construct(
        private readonly SaleRepository $saleRepository
    )
    {
    }

    // src/Controller/Api/Report/SalesReportController.php

    #[Route('/sales', name: 'api_sales_report', methods: ['GET'])]
    public function getSalesReport(Request $request): JsonResponse
    {
        try {
            $filters = [
                'startDate' => $request->query->get('startDate'),
                'endDate' => $request->query->get('endDate'),
                'search' => $request->query->get('search'),
            ];

            $current = $request->query->getInt('current', 1);
            $pageSize = $request->query->getInt('pageSize', 10);

            $query = $this->saleRepository->getReportQuery($filters);
            $query->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            $paginator = new Paginator($query, true);
            $paginator->setUseOutputWalkers(false);

            // Mapeo manual para formatear FECHAS y TOTALES
            $results = array_map(function ($sale) {
                // 1. Obtenemos el valor crudo (asegurando que sea int)
                $statusRawValue = (int)(is_object($sale['status']) ? $sale['status']->value : $sale['status']);

                // 2. Intentamos obtener el Enum de forma segura
                $statusEnum = SaleStatus::tryFrom($statusRawValue) ?? SaleStatus::IN_PROGRESS;

                return [
                    'saleId' => $sale['saleId'],
                    'folio' => $sale['folio'],
                    'saleDate' => $sale['saleDate'] instanceof \DateTimeInterface
                        ? $sale['saleDate']->format('d/m/Y H:i:s')
                        : $sale['saleDate'],
                    'cashier' => $sale['cashier'],
                    'cashbox' => $sale['cashbox'],
                    'status' => $statusEnum->getLabel(),
                    'subtotal' => (float)$sale['subtotal'],
                    'tax' => (float)$sale['totalTax'],
                    'total' => (float)$sale['total'],
                    'change' => (float)($sale['change'] ?? 0),
                ];
            }, $paginator->getIterator()->getArrayCopy());

            $totals = $this->saleRepository->getTotalAccumulated($filters);

            return $this->json([
                'total' => count($paginator),
                'results' => $results,
                'current' => $current,
                'pageSize' => $pageSize,
                'summary' => [
                    'totalAccumulated' => $totals['totalSales'],
                    'totalChange' => $totals['totalChange'],
                    'netIncome' => $totals['netCash'], // Total ventas - Total cambio
                    'count' => count($paginator)
                ],
                'status' => Response::HTTP_OK
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar el reporte',
                'detail' => $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/sales/export', name: 'api_sales_report_export', methods: ['GET'])]
    public function exportSalesReportCsv(Request $request): StreamedResponse
    {
        $filters = [
            'startDate' => $request->query->get('startDate'),
            'endDate' => $request->query->get('endDate'),
            'search' => $request->query->get('search'),
        ];

        // Obtenemos los datos sin límites de paginación
        $sales = $this->saleRepository->getExportData($filters);

        $response = new StreamedResponse(function () use ($sales) {
            $handle = fopen('php://output', 'w+');

            // Añadir BOM para compatibilidad con Excel (UTF-8)
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Cabeceras del archivo
            fputcsv($handle, [
                'FOLIO',
                'FECHA',
                'CAJERO',
                'CAJA',
                'ESTADO',
                'SUBTOTAL',
                'IMPUESTOS',
                'TOTAL',
                'CAMBIO'
            ], ';');

            foreach ($sales as $sale) {
                // Formatear el estado usando tu Enum (basado en tu lógica del controlador)
                $statusRawValue = (int)$sale['status'];
                $statusEnum = SaleStatus::tryFrom($statusRawValue) ?? SaleStatus::IN_PROGRESS;

                // Formatear fecha
                $date = new \DateTime($sale['saleDate']);

                fputcsv($handle, [
                    $sale['folio'],
                    $date->format('d/m/Y H:i:s'),
                    $sale['cashier'],
                    $sale['cashbox'],
                    $statusEnum->getLabel(),
                    number_format((float)$sale['subtotal'], 2, '.', ''),
                    number_format((float)$sale['totalTax'], 2, '.', ''),
                    number_format((float)$sale['total'], 2, '.', ''),
                    number_format((float)($sale['change'] ?? 0), 2, '.', ''),
                ], ';');
            }

            fclose($handle);
        });

        // Configuración de cabeceras HTTP
        $fileName = 'reporte_ventas_' . date('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}
