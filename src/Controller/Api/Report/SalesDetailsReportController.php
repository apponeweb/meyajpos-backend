<?php

namespace App\Controller\Api\Report;

use App\Entity\SaleDetail;
use App\Repository\SaleDetailRepository;
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
        private readonly SaleDetailRepository $detailRepository
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

            // 1. Obtener la consulta y paginar
            $query = $this->detailRepository->getDetailsReportQuery($filters);
            $query->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            $paginator = new Paginator($query, true);

            // 2. Mapear los resultados a un array plano
            $results = array_map(function ($sd) {
                /** @var \App\Entity\SaleDetail $sd */
                $product = $sd->getProduct();
                $barber = $sd->getServiceProvider();
                $serviceType = $product ? $product->getServiceType() : null;

                $unitPrice = (float)$sd->getUnitPrice();
                $totalPrice = (float)$sd->getTotal();
                $tip = $totalPrice - $unitPrice;

                return [
                    'ticket' => $sd->getSale()->getFolio(),
                    'servProd' => $product ? $product->getName() : 'N/A',
                    'serviceType' => $serviceType ? $serviceType->getName() : 'N/A',
                    'barber' => $barber ? $barber->getName() : 'Sin asignar',
                    'quantity' => (float)$sd->getQuantity(),
                    'unitPrice' => number_format($unitPrice, 2, '.', ','),
                    'tip' => number_format($tip, 2, '.', ','),
                    'total' => number_format($totalPrice, 2, '.', ','),
                    'date' => $sd->getSale()->getSaleDate()->format('d/m/Y H:i:s')
                ];
            }, $paginator->getIterator()->getArrayCopy());

            // --- SOLUCIÓN AL ERROR: Definir la variable $totals ---
            $totals = $this->detailRepository->getDetailsTotalAccumulated($filters);

            return $this->json([
                'total' => count($paginator),
                'results' => $results,
                'current' => $current,
                'pageSize' => $pageSize,
                'summary' => [
                    'totalQuantity' => number_format((float)($totals['sumQuantity'] ?? 0)),
                    'totalTips' => number_format((float)($totals['sumTips'] ?? 0), 2, '.', ','),
                    'totalAmount' => number_format((float)($totals['sumTotal'] ?? 0), 2, '.', ','),
                    'totalUnitPrice' => number_format((float)($totals['sumUnitPrice'] ?? 0)),
                ],
                'status' => Response::HTTP_OK
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar detalle',
                'detail' => $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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

        $data = $this->detailRepository->getDetailsExportData($filters);

        $response = new StreamedResponse(function () use ($data) {
            $handle = fopen('php://output', 'w+');
            // BOM para UTF-8 (Excel)
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Cabeceras coincidentes con tu imagen
            fputcsv($handle, ['TICKET', 'SERV/PROD', 'TIPO', 'BARBERO', 'CANTIDAD', 'PRECIO U.', 'PROPINA', 'TOTAL', 'FECHA'], ';');

            foreach ($data as $row) {
                $unitPrice = (float)$row['unitPrice'];
                $total = (float)$row['total'];
                $tip = $total - $unitPrice;

                // Formatear la fecha
                $dateStr = '';
                if (isset($row['saleDate'])) {
                    $date = new \DateTime($row['saleDate']);
                    $dateStr = $date->format('d/m/Y');
                }

                fputcsv($handle, [
                    $row['saleFolio'] ?? '',
                    $row['productName'] ?? '',
                    $row['serviceType'] ?? 'N/A',
                    $row['barberName'] ?? 'Sin asignar',
                    $row['quantity'],
                    number_format($unitPrice, 2, '.', ''),
                    number_format($tip, 2, '.', ''),
                    number_format($total, 2, '.', ''),
                    $dateStr
                ], ';');
            }
            fclose($handle);
        });

        $fileName = 'reporte_detalles_' . date('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}
