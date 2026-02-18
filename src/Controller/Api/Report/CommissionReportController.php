<?php

namespace App\Controller\Api\Report;

use App\Repository\CommissionGeneratedRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Route('/reports')]
class CommissionReportController extends AbstractController
{
    public function __construct(
        private readonly CommissionGeneratedRepository $repository
    )
    {
    }

    #[Route('/commissions', name: 'api_commissions_report', methods: ['GET'])]
    public function getCommissionReport(Request $request): JsonResponse
    {
        try {
            $filters = [
                'startDate' => $request->query->get('startDate'),
                'endDate' => $request->query->get('endDate'),
                'search' => $request->query->get('search'),
                'serviceTypeId' => $request->query->get('serviceTypeId'),
                'barberId' => $request->query->get('barberId'),
            ];

            $current = $request->query->getInt('current', 1);
            $pageSize = $request->query->getInt('pageSize', 10);

            $query = $this->repository->getReportQuery($filters);

            $query->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            $paginator = new Paginator($query, false);
            $resultsRaw = iterator_to_array($paginator->getIterator());

            $results = array_map(function ($row) {
                $dateObj = is_string($row['date']) ? new \DateTime($row['date']) : $row['date'];

                // LÓGICA DEL PRECIO:
                // Despejamos el precio: (Comisión total / cantidad) * 100 / porcentaje
                // O más simple por fila: (Comisión / Porcentaje) * 100
                $percentage = (float)$row['percentage'];
                $totalComm = (float)$row['totalCommission'];
                $quantity = (int)$row['quantity'];

                $unitCommission = $quantity > 0 ? $totalComm / $quantity : 0;
                $price = ($percentage > 0) ? ($unitCommission * 100) / $percentage : 0;

                return [
                    'service' => $row['service'],
                    'serviceType' => $row['serviceType'] ?? 'N/A', // Campo que agregamos en la Query
                    'barber' => $row['barber'],
                    'price' => number_format((float)$price, 2, '.', ','), // Nuevo campo
                    'percentage' => number_format($percentage, 2, '.', ','),
                    'quantity' => $quantity,
                    'total' => number_format((float)$price * $quantity, 2, '.', ','),
                    'commission' => number_format($totalComm, 2, '.', ','),
                    'date' => $dateObj instanceof \DateTimeInterface
                        ? $dateObj->format('d/m/Y H:i')
                        : $dateObj,
                ];
            }, $resultsRaw);

            $summary = $this->repository->getTotalSummary($filters);

            return $this->json([
                'total' => count($paginator),
                'results' => $results,
                'current' => $current,
                'pageSize' => $pageSize,
                'summary' => [
                    'totalCommission' => number_format((float)($summary['totalAmount'] ?? 0), 2, '.', ','),
                    'totalServices' => (int)($summary['totalCount'] ?? 0)
                ],
                'status' => Response::HTTP_OK
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar el reporte de comisiones',
                'detail' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    #[Route('/commissions/export', name: 'api_commissions_export', methods: ['GET'])]
    public function exportCommissionsCsv(Request $request): StreamedResponse
    {
        $filters = [
            'startDate' => $request->query->get('startDate'),
            'endDate' => $request->query->get('endDate'),
            'search' => $request->query->get('search'),
            'serviceTypeId' => $request->query->get('serviceTypeId'), // Nuevo filtro
        ];

        // Obtenemos los datos (Asegúrate de que getExportData use la misma lógica que getReportQuery)
        $data = $this->repository->getReportQuery($filters)->getResult();

        $response = new StreamedResponse(function () use ($data) {
            $handle = fopen('php://output', 'w+');

            // Añadir BOM para compatibilidad con Excel (UTF-8)
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Cabeceras actualizadas
            fputcsv($handle, [
                'PRODUCTO/SERVICIO',
                'BARBERO',
                'PRECIO',
                'CANTIDAD',
                'TOTAL',
                'COMISIÓN %',
                'MONTO COMISIÓN',
                'FECHA'
            ], ';');

            foreach ($data as $row) {
                $date = $row['date'] instanceof \DateTimeInterface ? $row['date'] : new \DateTime($row['date']);

                // Lógica de cálculo de precio (misma que en el controlador)
                $percentage = (float)$row['percentage'];
                $totalComm = (float)$row['totalCommission'];
                $quantity = (int)$row['quantity'];

                $unitCommission = $quantity > 0 ? $totalComm / $quantity : 0;
                $unitPrice = ($percentage > 0) ? ($unitCommission * 100) / $percentage : 0;

                fputcsv($handle, [
                    $row['service'],
                    $row['barber'],
                    number_format((float)$unitPrice, 2, '.', ''),
                    $quantity,
                    number_format((float)$unitPrice * $quantity, 2, '.', ','),
                    number_format($percentage, 2, '.', ''),
                    number_format($totalComm, 2, '.', ''),
                    $date->format('d/m/Y H:i')
                ], ';');
            }

            fclose($handle);
        });

        $fileName = 'reporte_comisiones_' . date('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}
