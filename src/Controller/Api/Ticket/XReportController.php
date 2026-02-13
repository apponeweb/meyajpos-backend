<?php

namespace App\Controller\Api\Ticket;

use App\Enum\SaleStatus;
use App\Repository\XReportRepository;
use App\Service\XReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;

#[Route('/ticket/x-reports')]
class XReportController extends AbstractController
{
    public function __construct(
        private readonly XReportRepository $xReportRepository,
        private readonly XReportService    $xReportService
    )
    {
    }

    #[Route('/', name: 'api_x_reports_list', methods: ['GET'])]
    public function getXReports(Request $request): JsonResponse
    {
        try {
            $filters = [
                'startDate' => $request->query->get('startDate'),
                'endDate' => $request->query->get('endDate'),
                'search' => $request->query->get('search'),
            ];

            $current = $request->query->getInt('current', 1);
            $pageSize = $request->query->getInt('pageSize', 10);

            // Usamos el repositorio de XReport
            $query = $this->xReportRepository->getReportQuery($filters);

            $query->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            $paginator = new Paginator($query, true);
            $paginator->setUseOutputWalkers(false);

            $results = array_map(function ($report) {
                return [
                    'id' => $report['id'],
                    'reportNumber' => $report['reportNumber'],
                    'date' => $report['xReportDate'] instanceof \DateTimeInterface
                        ? $report['xReportDate']->format('d/m/Y H:i:s')
                        : $report['xReportDate'],
                    'user' => $report['userName'],
                    'sessionId' => $report['sessionId'],
                    'systemTotal' => number_format((float)$report['systemTotal'], 2, '.', ','),
                    'declaredTotal' => number_format((float)$report['declaredTotal'], 2, '.', ','),
                    'difference' => number_format((float)$report['difference'], 2, '.', ','),
                    'observations' => $report['observations'],
                    // Semáforo visual para el frontend: si hay diferencia negativa o positiva
                    'hasDifference' => abs((float)$report['difference']) > 0.01
                ];
            }, $paginator->getIterator()->getArrayCopy());

            return $this->json([
                'total' => count($paginator),
                'results' => $results,
                'current' => $current,
                'pageSize' => $pageSize,
                'status' => Response::HTTP_OK
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar el listado de Cortes X',
                'detail' => $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Nuevo endpoint para generar la data del ticket
     */
    #[Route('/print/{id}', name: 'api_xreport_print_ticket', methods: ['GET'])]
    public function printTicket(int $id): Response
    {
        try {
            // 1. Buscar la venta por ID
            $xreport = $this->xReportRepository->find($id);

            if (!$xreport) {
                return $this->json([
                    'message' => 'Coret X no encontrada',
                    'status' => Response::HTTP_NOT_FOUND
                ], Response::HTTP_NOT_FOUND);
            }

            // 2. Generar la data usando el servicio
            // El servicio ya devuelve un string JSON
            $ticketJson = $this->xReportService->generateTicketData($xreport);

            // 3. Retornar una Response con el Content-Type adecuado
            return new Response(
                $ticketJson,
                Response::HTTP_OK,
                ['Content-Type' => 'application/json']
            );

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar la data del ticket',
                'detail' => $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
