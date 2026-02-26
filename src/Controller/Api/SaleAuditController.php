<?php

namespace App\Controller\Api;

use App\Entity\SaleAuditDeleted;
use App\Repository\SaleAuditDeletedRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/audit')]
class SaleAuditController extends AbstractController
{
    public function __construct(
        private readonly SaleAuditDeletedRepository $auditRepository
    )
    {
    }

    #[Route('/deleted_sales', name: 'api_audit_deleted_sales', methods: ['GET'])]
    public function getDeletedSales(Request $request): JsonResponse
    {
        try {
            $filters = [
                'startDate' => $request->query->get('startDate'),
                'endDate' => $request->query->get('endDate'),
                'search' => $request->query->get('search'),
                'originalUserId' => $request->query->get('originalUserId'), // Nuevo filtro
            ];

            $current = $request->query->getInt('current', 1);
            $pageSize = $request->query->getInt('pageSize', 10);

            // Obtenemos la query desde el repositorio (la crearemos en el siguiente paso)
            $query = $this->auditRepository->getAuditQuery($filters);
            $query->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            $paginator = new Paginator($query, true);

            // Mapeo manual similar a tu estructura de reporte
            $results = array_map(function (SaleAuditDeleted $audit) {
                $content = $audit->getContent();

                return [
                    'auditId' => $audit->getId(),
                    'folio' => $audit->getFolio(),
                    'deletedAt' => $audit->getDeletedAt()->format('d/m/Y H:i:s'),
                    'originalUser' => $content['user'] ?? 'N/A',
                    'originalSaleDate' => $content['sale_date'] ?? 'N/A',
                    'total' => number_format((float)$audit->getTotal(), 2, '.', ','),
                    'subtotal' => number_format((float)($content['subtotal'] ?? 0), 2, '.', ','),
                    'tax' => number_format((float)($content['tax'] ?? 0), 2, '.', ','),
                    // Detalles recuperados del JSON
                    'detailsCount' => count($content['details'] ?? []),
                    'paymentMethod' => $content['payments'][0]['method'] ?? 'Desconocido',
                    'auditDetail' => $content // Estructura completa por si se requiere expandir en UI
                ];
            }, iterator_to_array($paginator->getIterator()));

            // Totales acumulados de la auditoría (opcional)
            $totals = $this->auditRepository->getAuditTotals($filters);

            return $this->json([
                'total' => count($paginator),
                'results' => $results,
                'current' => $current,
                'pageSize' => $pageSize,
                'summary' => [
                    'totalAmountDeleted' => number_format((float)$totals['totalAmount'], 2, '.', ','),
                    'count' => count($paginator)
                ],
                'status' => Response::HTTP_OK
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar el reporte de auditoría',
                'detail' => $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
