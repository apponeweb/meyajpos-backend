<?php

namespace App\Controller\Api;

use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\XReport;
use App\Entity\XReportDetail;
use App\Enum\CashBoxSessionStatus;
use App\Enum\PaymentTypeEnum;
use App\Repository\CashBoxSessionRepository;
use App\Repository\CashBoxMovementRepository;
use App\Repository\XReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/x-reports')]
class XReportController extends AbstractController
{
    #[Route('/generate', name: 'app_x_report_generate', methods: ['POST'])]
    public function generate(Request       $request, CashBoxSessionRepository $cashBoxSessionRepository, XReportRepository $reportRepo, EntityManagerInterface $em,
                             UserInterface $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // 1. Validar sesión abierta
        $session = $cashBoxSessionRepository->findOneBy([
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ]);

        if (!$session) {
            return $this->json(['error' => 'No active session found'], 404);
        }

        // 2. Mapeo de JSON a Enums
        $paymentMapping = [
            'cash' => PaymentTypeEnum::CASH,
            'card' => PaymentTypeEnum::CARD,
            'transfer' => PaymentTypeEnum::TRAMSFER,
        ];

        // 3. Crear encabezado del reporte
        $report = new XReport();
        $report->setCashSession($session);
        $report->setUser($user);
        $report->setCreatedBy($user->getId());
        $report->setUpdatedBy($user->getId());
        $report->setXReportDate(new \DateTime());
        $report->setReportNumber($reportRepo->count(['cashSession' => $session]) + 1);
        $report->setObservations($data['observations'] ?? 'Manual X Report by Payment Type.');

        $saleRepository = $em->getRepository(Sale::class);
        $paymentTypeRepo = $em->getRepository(PaymentType::class);

        // 4. Procesar dinámicamente
        foreach ($paymentMapping as $jsonKey => $enumValue) {
            // Obtenemos la entidad PaymentType
            $paymentTypeEntity = $paymentTypeRepo->find($enumValue->value);
            if (!$paymentTypeEntity) continue;

            // Ahora $systemAmount es directamente el string devuelto por tu función
            $systemAmount = $saleRepository->getSummaryByPaymentType($session, $enumValue->value);

            // Si es efectivo, sumamos el fondo inicial de la sesión
            if ($enumValue === PaymentTypeEnum::CASH) {
                $systemAmount = bcadd($systemAmount, $session->getInitialAmount(), 2);
            }

            $detail = new XReportDetail();
            $detail->setPaymentType($paymentTypeEntity);
            $detail->setSystemAmount($systemAmount);
            $detail->setCreatedBy($user->getId());
            $detail->setUpdatedBy($user->getId());
            $detail->setDeclaredAmount((string)($data[$jsonKey] ?? '0.00'));

            $report->addDetail($detail);
        }

        $em->persist($report);
        $em->flush();

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $report->getId(),
                'report_number' => $report->getReportNumber(),
                'system_total' => $report->getSystemTotal(),
                'declared_total' => $report->getDeclaredTotal(),
                'difference' => $report->getDifference()
            ]
        ], 201);
    }
}
