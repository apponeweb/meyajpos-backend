<?php

namespace App\Controller\Api\Ticket;

use App\Enum\SaleStatus;
use App\Repository\SaleRepository;
use App\Service\SaleService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;

#[Route('/ticket/sales')]
class SalesController extends AbstractController
{
    public function __construct(
        private readonly SaleRepository $saleRepository,
        private readonly SaleService $salesService
    ) {
    }

    #[Route('/list', name: 'api_sales_ticket', methods: ['GET'])]
    public function getSales(Request $request): JsonResponse
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
                $statusRawValue = (int) (is_object($sale['status']) ? $sale['status']->value : $sale['status']);

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
                    'subtotal' => number_format((float) $sale['subtotal'], 2, '.', ','),
                    'tax' => number_format((float) $sale['totalTax'], 2, '.', ','),
                    'total' => number_format((float) $sale['total'], 2, '.', ','),
                    'change' => number_format((float) ($sale['change'] ?? 0), 2, '.', ',')
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
                'message' => 'Error al generar el reporte',
                'detail' => $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Nuevo endpoint para generar la data del ticket
     */
    #[Route('/print/{id}', name: 'api_sales_print_ticket', methods: ['GET'])]
    public function printTicket(int $id): Response
    {
        try {
            // 1. Buscar la venta por ID
            $sale = $this->saleRepository->find($id);

            if (!$sale) {
                return $this->json([
                    'message' => 'Venta no encontrada',
                    'status' => Response::HTTP_NOT_FOUND
                ], Response::HTTP_NOT_FOUND);
            }

            // 2. Generar la data usando el servicio
            // El servicio ya devuelve un string JSON
            $ticketJson = $this->salesService->generateTicketData($sale);

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

    #[Route('/pdf/{id}', name: 'api_sales_pdf_ticket', methods: ['GET'])]
    public function generateSalePdf(int $id): Response
    {
        try {
            // 1. Buscar la venta
            $sale = $this->saleRepository->find($id);

            if (!$sale) {
                return $this->json([
                    'message' => 'Venta no encontrada',
                    'status' => Response::HTTP_NOT_FOUND
                ], Response::HTTP_NOT_FOUND);
            }

            // 2. Generar y decodificar la data (Limpieza del JSON)
            $fullData = json_decode($this->salesService->generateTicketData($sale), true);

            // Al igual que el reporte, extraemos el objeto real
            $ticketData = $fullData[0]['data'] ?? null;

            if (!$ticketData) {
                throw new \Exception("La estructura del ticket es inválida.");
            }

            // 3. Configurar Dompdf
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Helvetica');
            $pdfOptions->set('isRemoteEnabled', true);

            $dompdf = new Dompdf($pdfOptions);

            // 4. Renderizar la plantilla Twig (La propuesta de diseño de ticket que te di)
            $html = $this->renderView('ticket/sale.html.twig', [
                'data' => $ticketData
            ]);

            // 5. Generar PDF
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait'); // O 'A4' si prefieres el formato grande que pediste
            $dompdf->render();

            // 6. Retorno del PDF
            return new Response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="ticket_venta_' . $sale->getId() . '.pdf"'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar el PDF de la venta',
                'detail' => $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/html/{id}', name: 'api_sales_html_ticket', methods: ['GET'])]
    public function generateSaleHtml(int $id): Response
    {
        try {
            $sale = $this->saleRepository->find($id);

            if (!$sale) {
                return $this->json([
                    'message' => 'Venta no encontrada',
                    'status' => Response::HTTP_NOT_FOUND
                ], Response::HTTP_NOT_FOUND);
            }

            $fullData = json_decode($this->salesService->generateTicketData($sale), true);
            $ticketData = $fullData[0]['data'] ?? null;

            if (!$ticketData) {
                throw new \Exception("La estructura del ticket es inválida.");
            }

            $html = $this->renderView('ticket/sale.html.twig', [
                'data' => $ticketData
            ]);

            return new Response($html, 200, [
                'Content-Type' => 'text/html'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al generar el HTML de la venta',
                'detail' => $e->getMessage(),
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
