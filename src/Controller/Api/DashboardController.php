<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\AppointmentService;
use App\Entity\BarberSchedule;
use App\Entity\InventoryStock;
use App\Entity\Sale;
use App\Entity\SaleDetail;
use App\Entity\User;
use App\Entity\BarberProfile;
use App\Enum\AppointmentStatus;
use App\Enum\SaleStatus;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DashboardController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Rest\Get('/dashboard/summary')]
    public function summary(Request $request): JsonResponse
    {
        $branchId = $request->query->get('branchId');
        
        $todayStart = new \DateTime('today');
        $todayEnd = clone $todayStart;
        $todayEnd->modify('+1 day')->modify('-1 second');
        
        $monthStart = new \DateTime('first day of this month 00:00:00');
        
        $dayOfWeek = (int)(new \DateTime())->format('N'); // 1 (Mon) - 7 (Sun)
        
        // ------------------------------
        // 1. KPIs
        // ------------------------------
        
        // Citas de Hoy
        $qbCitas = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT a.id) as total, SUM(CASE WHEN a.status IN (1, 2, 6) THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN a.status = 4 THEN 1 ELSE 0 END) as completed')
            ->from(AppointmentService::class, 'asrv')
            ->join('asrv.appointment', 'a')
            ->where('asrv.scheduledDateTime >= :start AND asrv.scheduledDateTime <= :end')
            ->andWhere('a.status != 3'); // Exclude cancelled appointments from total
        
        if ($branchId) {
            $qbCitas->andWhere('a.branch = :branchId')->setParameter('branchId', $branchId);
        }
        $qbCitas->setParameter('start', $todayStart)->setParameter('end', $todayEnd);
        $citasHoy = $qbCitas->getQuery()->getSingleResult();
        
        // Ocupación Barberos (Barberos con horario hoy vs total de barberos activos)
        $qbBarbersActive = $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.barberSn = true');
        
        $totalBarberos = $qbBarbersActive->getQuery()->getSingleScalarResult();

        $qbBarberosWorking = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT b.id)')
            ->from(BarberSchedule::class, 'bs')
            ->join('bs.barber', 'b')
            ->where('bs.dayOfWeek = :dia')
            ->andWhere('bs.deletedAt IS NULL');
        if ($branchId) {
            $qbBarberosWorking->andWhere('bs.branch = :branchId')->setParameter('branchId', $branchId);
        }
        $qbBarberosWorking->setParameter('dia', $dayOfWeek);
        $barberosTrabajando = $qbBarberosWorking->getQuery()->getSingleScalarResult();

        // Recaudación Hoy & Ticket Promedio
        $qbSales = $this->em->createQueryBuilder()
            ->select('SUM(s.total) as totalReal, COUNT(s.id) as countSales')
            ->from(Sale::class, 's')
            ->join('s.cashBox', 'cb')
            ->where('s.saleDate >= :start AND s.saleDate <= :end')
            ->andWhere('s.status = :statusPaid');

        if ($branchId) {
            $qbSales->andWhere('cb.branch = :branchId')->setParameter('branchId', $branchId);
        }

        $qbSales->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->setParameter('statusPaid', SaleStatus::PAID->value);
            
        $salesToday = $qbSales->getQuery()->getSingleResult();
        $totalRecaudado = (float)($salesToday['totalReal'] ?? 0);
        $ventasCount = (int)($salesToday['countSales'] ?? 0);
        $ticketPromedio = $ventasCount > 0 ? $totalRecaudado / $ventasCount : 0;

        // ------------------------------
        // 2. Gráficos
        // ------------------------------
        
        // Ingresos últimos 7 días
        $sevenDaysAgo = clone $todayStart;
        $sevenDaysAgo->modify('-6 days');
        
        $qb7Days = $this->em->createQueryBuilder()
            ->select('s.saleDate as dt, s.total as totalDia')
            ->from(Sale::class, 's')
            ->join('s.cashBox', 'cb7')
            ->where('s.saleDate >= :start AND s.saleDate <= :end')
            ->andWhere('s.status = :statusPaid');

        if ($branchId) {
            $qb7Days->andWhere('cb7.branch = :branchId')->setParameter('branchId', $branchId);
        }

        $qb7Days->setParameter('start', $sevenDaysAgo)
            ->setParameter('end', $todayEnd)
            ->setParameter('statusPaid', SaleStatus::PAID->value);
            
        $rawSales = $qb7Days->getQuery()->getResult();
        $ingresos7DiasMap = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = clone $todayStart;
            $d->modify("-{$i} days");
            $ingresos7DiasMap[$d->format('Y-m-d')] = 0;
        }
        foreach ($rawSales as $rs) {
            $dt = clone $rs['dt'];
            $dateStr = $dt->format('Y-m-d');
            if (isset($ingresos7DiasMap[$dateStr])) {
                $ingresos7DiasMap[$dateStr] += (float)$rs['totalDia'];
            }
        }
        $ingresos7Dias = [];
        foreach ($ingresos7DiasMap as $k => $v) {
            $ingresos7Dias[] = ['dt' => $k, 'totalDia' => $v];
        }
        
        // Top 5 Servicios del mes
        $qbTopServices = $this->em->createQueryBuilder()
            ->select('p.name as servicio, COUNT(sd.id) as cantidad')
            ->from(SaleDetail::class, 'sd')
            ->join('sd.sale', 's')
            ->join('sd.product', 'p')
            ->join('s.cashBox', 'cbts')
            ->where('s.saleDate >= :startMonth AND s.status = :statusPaid')
            ->groupBy('p.id')
            ->orderBy('cantidad', 'DESC')
            ->setMaxResults(5)
            ->setParameter('startMonth', $monthStart)
            ->setParameter('statusPaid', SaleStatus::PAID->value);

        if ($branchId) {
            $qbTopServices->andWhere('cbts.branch = :branchId')->setParameter('branchId', $branchId);
        }

        $topServicios = $qbTopServices->getQuery()->getResult();
        
        // ------------------------------
        // 3. Tablas Operativas
        // ------------------------------
        
        // Próximas Citas Hoy
        $qbProximas = $this->em->createQueryBuilder()
            ->select('asrv.scheduledDateTime, c.name as customerName, uBarber.name as barberName, bProfile.photoUrl as barberPhoto, a.status as status')
            ->from(AppointmentService::class, 'asrv')
            ->join('asrv.appointment', 'a')
            ->join('a.customer', 'c')
            ->join('asrv.barber', 'bProfile')
            ->join('bProfile.user', 'uBarber')
            ->where('asrv.scheduledDateTime >= :now AND asrv.scheduledDateTime <= :end')
            ->andWhere('a.status IN (1, 2, 6)') // PENDING, CONFIRMED, IN_PROCESS
            ->orderBy('asrv.scheduledDateTime', 'ASC')
            ->setMaxResults(10);
            
        if ($branchId) {
            $qbProximas->andWhere('a.branch = :branchId')->setParameter('branchId', $branchId);
        }
        
        $qbProximas->setParameter('now', new \DateTime())
            ->setParameter('end', $todayEnd);
            
        $proximasCitas = $qbProximas->getQuery()->getResult();
        
        // Ranking Barberos (Mes Actual)
        $qbRanking = $this->em->createQueryBuilder()
            ->select('u.name as barberName, u.lastName as barberLastName, bp.photoUrl as barberPhoto, SUM(sd.total) as totalGenerado, COUNT(sd.id) as serviciosRealizados')
            ->from(SaleDetail::class, 'sd')
            ->join('sd.serviceProvider', 'u')
            ->join('sd.sale', 's')
            ->join('s.cashBox', 'cbr')
            ->leftJoin(BarberProfile::class, 'bp', \Doctrine\ORM\Query\Expr\Join::WITH, 'bp.user = u')
            ->where('s.saleDate >= :startMonth')
            ->andWhere('s.status = :statusPaid')
            ->groupBy('u.id')
            ->orderBy('serviciosRealizados', 'DESC')
            ->setMaxResults(5)
            ->setParameter('startMonth', $monthStart)
            ->setParameter('statusPaid', SaleStatus::PAID->value);

        if ($branchId) {
            $qbRanking->andWhere('cbr.branch = :branchId')->setParameter('branchId', $branchId);
        }

        $rankingBarberos = $qbRanking->getQuery()->getResult();

        // ------------------------------
        // 4. Fidelización (Top Clientes)
        // ------------------------------
        
        $qbTopClientes = $this->em->createQueryBuilder()
            ->select('c.name as customerName, c.phone as customerPhone, COUNT(DISTINCT a.id) as citas, SUM(asrv.price) as totalGastado')
            ->from(AppointmentService::class, 'asrv')
            ->join('asrv.appointment', 'a')
            ->join('a.customer', 'c')
            ->where('a.status = :statusCompleted')
            ->andWhere('asrv.scheduledDateTime >= :startMonth')
            ->groupBy('c.id')
            ->orderBy('totalGastado', 'DESC')
            ->setMaxResults(5)
            ->setParameter('startMonth', $monthStart)
            ->setParameter('statusCompleted', AppointmentStatus::COMPLETED->value);
            
        if ($branchId) {
            $qbTopClientes->andWhere('a.branch = :branchId')->setParameter('branchId', $branchId);
        }
        
        $topClientes = $qbTopClientes->getQuery()->getResult();

        return $this->json([
            'kpis' => [
                'citasTotal' => (int)($citasHoy['total'] ?? 0),
                'citasPendientes' => (int)($citasHoy['pending'] ?? 0),
                'citasCompletadas' => (int)($citasHoy['completed'] ?? 0),
                'barberosActivos' => $barberosTrabajando,
                'barberosTotal' => $totalBarberos,
                'recaudacionDia' => $totalRecaudado,
                'ticketPromedio' => $ticketPromedio
            ],
            'charts' => [
                'ingresos7Dias' => $ingresos7Dias,
                'topServicios'  => $topServicios
            ],
            'lists' => [
                'proximasCitas' => $proximasCitas,
                'rankingBarberos' => $rankingBarberos
            ],
            'alerts' => [
                'topClientes' => $topClientes
            ]
        ], Response::HTTP_OK);
    }
}
