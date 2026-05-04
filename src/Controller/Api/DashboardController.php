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

        // Rango de fechas: si vienen dateFrom/dateTo los usamos, sino el día de hoy
        $dateFromRaw = $request->query->get('dateFrom');
        $dateToRaw   = $request->query->get('dateTo');

        $todayStart = $dateFromRaw
            ? new \DateTime($dateFromRaw . ' 00:00:00')
            : new \DateTime('today');

        $todayEnd = $dateToRaw
            ? new \DateTime($dateToRaw . ' 23:59:59')
            : (clone $todayStart)->modify('+1 day')->modify('-1 second');

        // Para queries que usaban "mes actual" usamos el inicio del rango
        $monthStart = clone $todayStart;

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
        
        // Ingresos por día dentro del rango seleccionado
        // Si no hay filtro, mostrar los últimos 7 días
        $chartStart = $dateFromRaw ? clone $todayStart : (clone $todayStart)->modify('-6 days');
        $chartEnd   = clone $todayEnd;

        // Determinar agrupación según la duración del rango
        $diff = $chartStart->diff($chartEnd)->days;
        $grouping = 'day';
        if ($diff > 60) {
            $grouping = 'month';
        } elseif ($diff > 14) {
            $grouping = 'week';
        }

        $qbChart = $this->em->createQueryBuilder()
            ->select('s.saleDate as dt, s.total as totalDia')
            ->from(Sale::class, 's')
            ->join('s.cashBox', 'cbChart')
            ->where('s.saleDate >= :start AND s.saleDate <= :end')
            ->andWhere('s.status = :statusPaid');

        if ($branchId) {
            $qbChart->andWhere('cbChart.branch = :branchId')->setParameter('branchId', $branchId);
        }

        $qbChart->setParameter('start', $chartStart)
            ->setParameter('end', $chartEnd)
            ->setParameter('statusPaid', SaleStatus::PAID->value);

        $rawSales = $qbChart->getQuery()->getResult();

        // Construir mapa de agrupación con valor 0
        $ingresosChartMap = [];
        $cursor = clone $chartStart;
        
        while ($cursor <= $chartEnd) {
            if ($grouping === 'month') {
                $key = $cursor->format('Y-m-01');
                if (!isset($ingresosChartMap[$key])) $ingresosChartMap[$key] = 0;
                $cursor->modify('first day of next month');
            } elseif ($grouping === 'week') {
                $wCursor = clone $cursor;
                if ($wCursor->format('N') != 1) {
                    $wCursor->modify('last monday');
                }
                $key = $wCursor->format('Y-m-d');
                if (!isset($ingresosChartMap[$key])) $ingresosChartMap[$key] = 0;
                $cursor->modify('+1 week');
            } else {
                $key = $cursor->format('Y-m-d');
                $ingresosChartMap[$key] = 0;
                $cursor->modify('+1 day');
            }
        }

        foreach ($rawSales as $rs) {
            $date = clone $rs['dt'];
            if ($grouping === 'month') {
                $dateStr = $date->format('Y-m-01');
            } elseif ($grouping === 'week') {
                if ($date->format('N') != 1) {
                    $date->modify('last monday');
                }
                $dateStr = $date->format('Y-m-d');
            } else {
                $dateStr = $date->format('Y-m-d');
            }
            
            if (isset($ingresosChartMap[$dateStr])) {
                $ingresosChartMap[$dateStr] += (float)$rs['totalDia'];
            }
        }

        $ingresosChart = [];
        foreach ($ingresosChartMap as $k => $v) {
            $ingresosChart[] = ['dt' => $k, 'totalDia' => $v];
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
                'ingresos' => $ingresosChart,
                'grouping' => $grouping,
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
