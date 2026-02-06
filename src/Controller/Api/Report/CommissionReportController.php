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
            ];

            $current = $request->query->getInt('current', 1);
            $pageSize = $request->query->getInt('pageSize', 10);

            $query = $this->repository->getReportQuery($filters);

            // OPCIÓN SEGURA: Usamos el modo de hidratación escalar
            // y calculamos manualmente el offset/limit si el Paginator sigue dando problemas.
            $query->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            // Pasamos FALSE como segundo parámetro para indicar que no es una consulta de entidades
            $paginator = new Paginator($query, false);

            $resultsRaw = iterator_to_array($paginator->getIterator());

            $results = array_map(function ($row) {
                // Doctrine devuelve el resultado de MAX() como un string o un objeto DateTime
                // Dependiendo de la configuración, lo normalizamos:
                $dateObj = is_string($row['date']) ? new \DateTime($row['date']) : $row['date'];

                return [
                    'service' => $row['service'],
                    'barber' => $row['barber'],
                    'quantity' => (int)$row['quantity'],
                    'commission' => (float)$row['totalCommission'],
                    'date' => $dateObj instanceof \DateTimeInterface
                        ? $dateObj->format('d/m/Y H:i')
                        : $dateObj,
                ];
            }, $resultsRaw);

            $summary = $this->repository->getTotalSummary($filters);

            return $this->json([
                'total' => count($paginator), // Esto ahora funcionará
                'results' => $results,
                'current' => $current,
                'pageSize' => $pageSize,
                'summary' => [
                    'totalCommission' => (float)($summary['totalAmount'] ?? 0),
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
        ];

        // Obtenemos todos los datos sin paginar
        $data = $this->repository->getExportData($filters);

        $response = new StreamedResponse(function () use ($data) {
            $handle = fopen('php://output', 'w+');

            // Añadir BOM para que Excel reconozca tildes y caracteres especiales (UTF-8)
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Cabeceras del CSV
            fputcsv($handle, ['SERVICIO/PR', 'BARBERO', 'CANTIDAD', 'MONTO CMISION', 'FECHA'], ';');

            foreach ($data as $row) {
                $date = new \DateTime($row['date']);

                fputcsv($handle, [
                    $row['service'],
                    $row['barber'],
                    $row['quantity'],
                    number_format((float)$row['totalCommission'], 2, '.', ''),
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
