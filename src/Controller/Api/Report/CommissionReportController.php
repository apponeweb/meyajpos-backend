<?php

namespace App\Controller\Api\Report;

use App\Repository\CommissionGeneratedRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;

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

            // IMPORTANTE: Para Scalar Results en Doctrine 3+ o Symfony 5/6+
            // a veces es necesario obtener el array directamente:
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
}
