<?php

namespace App\Controller\Api;

use App\Entity\CashBoxMovement;
use App\Entity\CashBoxSession;
use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\SalePayment;
use App\Entity\Tip;
use App\Entity\XReport;
use App\Entity\ZReport;
use App\Entity\ZReportDetail;
use App\Enum\CashBoxSessionStatus;
use App\Repository\CashBoxSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/z-reports')]
class ZReportController extends AbstractController
{
    #[Route('/preview', name: 'app_z_report_preview', methods: ['GET'])]
    public function preview(
        CashBoxSessionRepository $sessionRepo,
        EntityManagerInterface   $em,
        UserInterface            $user
    ): JsonResponse
    {
        $session = $sessionRepo->findOneBy(['user' => $user, 'status' => CashBoxSessionStatus::OPEN]);

        if (!$session) {
            return $this->json(['error' => 'No hay una sesión de caja abierta.'], 404);
        }

        $data = $this->calculateSessionTotals($session, $em);

        // Formateamos para el JSON (quitamos objetos PaymentType)
        $data['details'] = array_map(function ($detail) {
            return [
                'payment_type_id' => $detail['payment_type_id'],
                'payment_type_name' => $detail['payment_type_name'],
                'amount' => $detail['system_amount'],
                'count' => $detail['transaction_count']
            ];
        }, $data['details']);

        return $this->json(['status' => 'success', 'data' => $data]);
    }

    #[Route('/generate', name: 'app_z_report_generate', methods: ['POST'])]
    public function generate(
        CashBoxSessionRepository $sessionRepo,
        EntityManagerInterface   $em,
        UserInterface            $user
    ): JsonResponse
    {
        $session = $sessionRepo->findOneBy(['user' => $user, 'status' => CashBoxSessionStatus::OPEN]);

        if (!$session) {
            return $this->json(['error' => 'No se encontró una sesión abierta para generar el corte.'], 404);
        }

        // --- EL SISTEMA CALCULA TODO, SIN PARÁMETROS EXTERNOS ---
        $calculated = $this->calculateSessionTotals($session, $em);

        // 1. Crear Entidad ZReport
        $zReport = new ZReport();
        $zReport->setCashBoxSession($session);

        if ($calculated['xReportId']) {
            $zReport->setXReport($em->getReference(XReport::class, $calculated['xReportId']));
        }

        $zReport->setFolioZ($calculated['folioZ']);
        $zReport->setUser($user);
        $zReport->setTotalSales($calculated['totalSales']);
        $zReport->setTotalTips($calculated['totalTips']);
        $zReport->setTotalCashIn($calculated['totalCashIn']);
        $zReport->setTotalCashOut($calculated['totalCashOut']);
        $zReport->setExpectedCash($calculated['expectedCash']);

        /**
         * Lógica de Cuadre Automático:
         * Como no recibimos parámetros, el 'declaredCash' del Z se asume como
         * el valor esperado por el sistema basándose en el último XReport.
         */
        $zReport->setDeclaredCash($calculated['expectedCash']);
        $zReport->setCashDifference('0.00'); // Por defecto, cuadra al 100%

        $zReport->setCreatedBy($user->getId());

        // 2. Persistir Detalles
        foreach ($calculated['details'] as $item) {
            $detail = new ZReportDetail();
            $detail->setZReport($zReport);
            $detail->setPaymentType($item['payment_type']);
            $detail->setAmount($item['system_amount']);
            $detail->setTransactionCount($item['transaction_count']);
            $detail->setCreatedBy($user->getId());
            $em->persist($detail);
        }

        // 3. CERRAR SESIÓN (Cambio de estado)
        $session->setStatus(CashBoxSessionStatus::CLOSED);
        $session->setClosingDate(new \DateTime());

        $em->persist($zReport);
        $em->flush();

        return $this->json([
            'status' => 'success',
            'message' => 'Corte Z generado exitosamente y sesión cerrada.',
            'data' => [
                'folio' => $zReport->getFolioZ()
            ]
        ], 201);
    }

    private function calculateSessionTotals(CashBoxSession $session, EntityManagerInterface $em): array
    {
        $saleRepo = $em->getRepository(Sale::class);
        $moveRepo = $em->getRepository(CashBoxMovement::class);
        $tipRepo = $em->getRepository(Tip::class);
        $xReportRepo = $em->getRepository(XReport::class);
        $paymentTypeRepo = $em->getRepository(PaymentType::class);

        // 1. Obtener el último XReport para extraer su declaración de efectivo
        $lastXReport = $xReportRepo->getLastXReport($session);

        // Si no hay XReport previo, usamos el monto inicial de la sesión como base
        $baseCash = $lastXReport ? $lastXReport->getDeclaredTotal() : $session->getInitialAmount();
        $lastXNumber = $lastXReport ? $lastXReport->getReportNumber() : 0;

        /**
         * IMPORTANTE: Aquí calculamos solo lo ocurrido DESPUÉS del último Corte X
         * o consolidamos la sesión. Si el XReport ya cerró un ciclo, sumamos
         * los movimientos nuevos.
         */
        $totalSalesCash = $saleRepo->getTotalCashSalesBySession($session);
        $totalTips = $tipRepo->getTotalTipsBySession($session);
        $totalExtractions = $moveRepo->getTotalWithdrawals($session);
        $totalIncome = $moveRepo->getTotalDeposits($session);

        // 2. Generar Folio y Fecha
        $now = new \DateTime();
        $folioZ = $this->generateZFolio($now, $em);

        // 3. CÁLCULO DEL EFECTIVO ESPERADO PARA EL CORTE Z
        // Tomamos la base del X y sumamos los movimientos de la sesión
        $expectedCash = bcadd($baseCash, $totalSalesCash, 2);
        $expectedCash = bcadd($expectedCash, $totalIncome, 2);
        $expectedCash = bcadd($expectedCash, $totalTips, 2);
        $expectedCash = bcsub($expectedCash, $totalExtractions, 2);

        // 4. DESGLOSE POR TIPO DE PAGO
        $allPaymentTypes = $paymentTypeRepo->findBy(['isActive' => true]);
        $details = [];
        $grandTotalSales = '0.00';

        foreach ($allPaymentTypes as $paymentType) {
            $paymentData = $em->getRepository(SalePayment::class)
                ->getSummaryBySessionAndType($session, $paymentType);

            $details[] = [
                'payment_type' => $paymentType,
                'payment_type_id' => $paymentType->getId(),
                'payment_type_name' => $paymentType->getName(),
                'system_amount' => $paymentData['amount'],
                'transaction_count' => $paymentData['count']
            ];

            $grandTotalSales = bcadd($grandTotalSales, $paymentData['amount'], 2);
        }

        return [
            "cashBoxSession" => $session->getId(),
            "xReportId" => $lastXReport ? $lastXReport->getId() : null,
            "xReportNumber" => $lastXNumber,
            "declaredCashFromX" => $baseCash, // <--- Este es el declaredTotal del Corte X
            "folioZ" => $folioZ,
            "closingDate" => $now->format("d/m/Y H:i:s"),
            "user" => $session->getUser()->getId(),
            "totalSales" => $grandTotalSales,
            "totalTips" => $totalTips,
            "totalCashIn" => $totalIncome,
            "totalCashOut" => $totalExtractions,
            "expectedCash" => $expectedCash,
            "details" => $details,
        ];
    }

    private function generateZFolio(\DateTime $date, EntityManagerInterface $em): string
    {
        $repository = $em->getRepository(ZReport::class);
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        $count = $repository->createQueryBuilder('z')
            ->select('COUNT(z.id)')
            ->where('z.closingDate BETWEEN :start AND :end')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        return sprintf('Z-%s-%04d', $date->format('Ymd'), (int)$count + 1);
    }
}
