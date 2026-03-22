<?php

namespace App\Controller\Api\Report;


use App\Entity\Report\DailyReport;
use App\Entity\BarberProfile;
use App\Repository\Report\DailyReportRepository;
use App\Repository\SalePaymentRepository;
use Doctrine\ORM\EntityManagerInterface;

// Mantenemos este solo para los totales si ya lo tienes listo
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
        private readonly DailyReportRepository $dailyReportRepository,
        private readonly EntityManagerInterface $entityManager,
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
                'paymentTypeId' => $request->query->get('paymentTypeId'),
                'search' => $request->query->get('search'),
            ];

            $current = $request->query->getInt('current', 1);
            $pageSize = $request->query->getInt('pageSize', 10);

            // 1. Consulta directa a la VISTA
            $query = $this->dailyReportRepository->getReportQuery($filters);
            $query->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            $paginator = new Paginator($query);
            $results = [];

            $scheme = $request->getScheme();
            $host = $request->getHttpHost();
            $baseUrl = $scheme . '://' . $host;
            $barberPhotoCache = [];

            /** @var DailyReport $row */
            foreach ($paginator as $row) {
                $barberId = $row->getBarberId();
                if (!isset($barberPhotoCache[$barberId])) {
                    $profile = $this->entityManager->getRepository(BarberProfile::class)->findOneBy(['user' => $barberId]);
                    $barberPhotoCache[$barberId] = $profile?->getPhotoUrl();
                }
                $photoUrl = $barberPhotoCache[$barberId];

                $results[] = [
                    'id' => $row->getId(),
                    'ticket' => $row->getTicketFolio(),
                    'servProd' => $row->getProductServiceName(),
                    'serviceType' => $row->getServiceTypeName(),
                    'barber' => $row->getBarberName(),
                    'barberPhoto' => $photoUrl ? $baseUrl . $photoUrl : null,
                    'paymentMethod' => $row->getPaymentMethod(),
                    'paymentAmount' => number_format((float)$row->getPaymentAmount(), 2, '.', ','),
                    'quantity' => (float)$row->getQuantity(),
                    'unitPrice' => number_format((float)$row->getUnitPrice(), 2, '.', ','),
                    'total' => number_format((float)$row->getTotal(), 2, '.', ','),
                    'tip' => number_format((float)$row->getTipAmount(), 2, '.', ','),
                    'cashChange' => number_format((float)$row->getCashChange(), 2, '.', ','),
                    'date' => $row->getFormattedSaleDate(),
                    'cashBox' => $row->getCashBoxName()
                ];
            }

            // 3. Totales (Seguimos usando el acumulado optimizado de SalePayment)
            $totals = $this->dailyReportRepository->getDetailsTotalAccumulated($filters);

            return $this->json([
                'total' => count($paginator),
                'results' => $results,
                'current' => $current,
                'pageSize' => $pageSize,
                'summary' => [
                    'totalQuantity' => number_format((float)($totals['sumQuantity'] ?? 0), 2, '.', ','),
                    'totalAmount' => number_format((float)($totals['sumTotal'] ?? 0), 2, '.', ','),
                    'transfer' => number_format((float)($totals['totalTransfer'] ?? 0), 2, '.', ','),
                    'totalTips' => number_format((float)($totals['sumTips'] ?? 0), 2, '.', ','),
                    'card' => number_format((float)($totals['totalCard'] ?? 0), 2, '.', ','),
                    'cash' => number_format((float)($totals['totalCash'] ?? 0), 2, '.', ','),
                    'totalUnitPrice' => number_format((float)($totals['sumUnitPrice'] ?? 0), 2, '.', ','),
                ],
                'status' => Response::HTTP_OK
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar detalle',
                'detail' => $e->getMessage()
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
            'paymentTypeId' => $request->query->get('paymentTypeId'),
            'search' => $request->query->get('search'),
        ];

        // Obtenemos todos los registros de la vista sin paginar
        $data = $this->dailyReportRepository->getReportQuery($filters)->getResult();

        $response = new StreamedResponse(function () use ($data) {
            $handle = fopen('php://output', 'w+');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, ['TICKET', 'CAJA', 'PRODUCTO/SERVICIO', 'TIPO DE SERVICIO', 'BARBERO', 'CANTIDAD', 'PRECIO UNITARIO', 'PROPINA', 'METODO DE PAGO', 'MONTO PAGADO', 'CAMBIO', 'TOTAL', 'FECHA'], ';');

            /** @var DailyReport $row */
            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->getTicketFolio(),
                    $row->getCashBoxName(),
                    $row->getProductServiceName(),
                    $row->getServiceTypeName(),
                    $row->getBarberName(),
                    $row->getQuantity(),
                    $row->getUnitPrice(),
                    $row->getTipAmount(),
                    $row->getPaymentMethod(),
                    $row->getPaymentAmount(),
                    $row->getCashChange(),
                    $row->getTotal(),
                    $row->getFormattedSaleDate()
                ], ';');
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="reporte_diario.csv"');

        return $response;
    }
}
