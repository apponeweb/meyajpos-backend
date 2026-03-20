<?php

namespace App\Command;

use App\Entity\Appointment;
use App\Entity\AppointmentService;
use App\Entity\BarberProfile;
use App\Entity\BarberSchedule;
use App\Entity\Branch;
use App\Entity\CashBox;
use App\Entity\Customer;
use App\Entity\InventoryStock;
use App\Entity\MasterProduct;
use App\Entity\Sale;
use App\Entity\SaleDetail;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Enum\SaleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:dashboard:generate-dummy',
    description: 'Generates dummy data to visualize the dashboard properly.',
)]
class GenerateDashboardDataCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generando datos de prueba para el Dashboard...');

        // 1. Obtener entidades base necesarias
        $branch = $this->em->getRepository(Branch::class)->findOneBy([]);
        $customer = $this->em->getRepository(Customer::class)->findOneBy([]);
        $cashBox = $this->em->getRepository(CashBox::class)->findOneBy([]);
        
        $barberUser = $this->em->getRepository(User::class)->findOneBy(['barberSn' => true]);
        $barberProfile = $this->em->getRepository(BarberProfile::class)->findOneBy(['user' => $barberUser]);
        $adminUser = $this->em->getRepository(User::class)->findOneBy(['barberSn' => false]) ?? $barberUser;
        
        $product = $this->em->getRepository(MasterProduct::class)->findOneBy(['isInventoriable' => true]);
        $services = $this->em->getRepository(MasterProduct::class)->findBy(['isInventoriable' => false]);
        if (empty($services)) {
            $services = [$product];
        }

        if (!$branch || !$customer || !$barberUser || !$barberProfile || !$product || !$cashBox) {
            $io->error('Faltan datos maestros en la base de datos (Sucursal, Cliente, Barbero, Producto, Caja). Por favor crea al menos uno de cada uno desde el sistema antes de ejecutar este comando.');
            return Command::FAILURE;
        }

        $today = new \DateTime();

        $io->text('Calculando todos los días del mes actual...');
        $firstDayOfMonth = clone $today;
        $firstDayOfMonth->modify('first day of this month');
        $lastDayOfMonth = clone $today;
        $lastDayOfMonth->modify('last day of this month');

        $interval = new \DateInterval('P1D');
        $datePeriod = new \DatePeriod($firstDayOfMonth, $interval, $lastDayOfMonth->modify('+1 day'));
        
        $salesToUpdateDates = [];

        $io->text('Generando Agenda, Citas y Ventas para todo el mes...');
        foreach ($datePeriod as $currentDate) {
            $currentDayOfWeek = (int)$currentDate->format('N');

            // --- 1. Turno de Barbero para el día ---
            $schedule = new BarberSchedule();
            $schedule->setBarber($barberUser);
            $schedule->setBranch($branch);
            $schedule->setDayOfWeek($currentDayOfWeek);
            $schedule->setOpenTime(new \DateTime('08:00'));
            $schedule->setCloseTime(new \DateTime('18:00'));
            $schedule->setValidFrom($currentDate);
            $this->em->persist($schedule);

            // --- 2. Citas para el día ---
            // Cita Pendiente (1 hora después de las 10:00)
            $apptPending = new Appointment();
            $apptPending->setBranch($branch);
            $apptPending->setCustomer($customer);
            $apptPending->setStatus(AppointmentStatus::PENDING);
            $apptPending->setTotalAmount('350.00');
            $this->em->persist($apptPending);

            $apptSrvPending = new AppointmentService();
            $apptSrvPending->setAppointment($apptPending);
            $apptSrvPending->setService($services[array_rand($services)]);
            $apptSrvPending->setBarber($barberProfile);
            $apptTime1 = clone $currentDate;
            $apptTime1->setTime(rand(8, 12), rand(0, 59), rand(0, 59)); 
            $apptSrvPending->setScheduledDateTime($apptTime1);
            $apptSrvPending->setPrice('350.00');
            $this->em->persist($apptSrvPending);

            // Cita Completada
            $apptDone = new Appointment();
            $apptDone->setBranch($branch);
            $apptDone->setCustomer($customer);
            $apptDone->setStatus(AppointmentStatus::COMPLETED);
            $apptDone->setTotalAmount('200.00');
            $this->em->persist($apptDone);

            $apptSrvDone = new AppointmentService();
            $apptSrvDone->setAppointment($apptDone);
            $apptSrvDone->setService($services[array_rand($services)]);
            $apptSrvDone->setBarber($barberProfile);
            $apptTime2 = clone $currentDate;
            $apptTime2->setTime(rand(13, 17), rand(0, 59), rand(0, 59));
            $apptSrvDone->setScheduledDateTime($apptTime2);
            $apptSrvDone->setPrice('200.00');
            $this->em->persist($apptSrvDone);

            // --- 3. Ventas para el día ---
            $salesCount = rand(1, 3);
            for ($j = 0; $j < $salesCount; $j++) {
                $sale = new Sale();
                $folioDummy = 'DUMMY-' . $currentDate->format('Ymd') . '-' . rand(100, 9999);
                $sale->setFolio($folioDummy);
                $saleTime = clone $currentDate;
                $saleTime->setTime(rand(9, 17), rand(0, 59));
                $sale->setCashBox($cashBox);
                $sale->setUser($adminUser);
                $sale->setStatus(SaleStatus::PAID);
                
                $monto = rand(150, 600);
                $sale->setSubtotal((string)$monto);
                $sale->setTotal((string)$monto);
                $this->em->persist($sale);

                $detail = new SaleDetail();
                $detail->setSale($sale);
                $detail->setProduct($services[array_rand($services)]);
                $detail->setQuantity('1.000');
                $detail->setUnitPrice((string)$monto);
                $detail->setSubtotal((string)$monto);
                $detail->setTotal((string)$monto);
                $detail->setTax('0.00');
                $detail->setServiceProvider($barberUser);
                $this->em->persist($detail);
                
                $salesToUpdateDates[] = [
                    'folio' => $folioDummy,
                    'date' => $saleTime->format('Y-m-d H:i:s')
                ];
            }
        }

        $this->em->flush();
        
        $io->text('Aplicando override nativo para las fechas de las ventas (bypass PrePersist)...');
        $conn = $this->em->getConnection();
        foreach ($salesToUpdateDates as $s) {
            $conn->executeStatement('UPDATE tbd_sale SET sale_date = :date WHERE folio = :folio', [
                'date' => $s['date'],
                'folio' => $s['folio']
            ]);
        }
        // ---------------------------------------------------------
        // D. Inventario Bajo (InventoryStock)
        // ---------------------------------------------------------
        $io->text('Generando Alertas de Inventario...');
        
        // Buscar si ya existe stock para no duplicar llaves, o crearlo
        $stock = $this->em->getRepository(InventoryStock::class)->findOneBy([
            'branch' => $branch,
            'masterProduct' => $product
        ]);
        
        if (!$stock) {
            $stock = new InventoryStock();
            $stock->setBranch($branch);
            $stock->setMasterProduct($product);
            $this->em->persist($stock);
        }
        
        $stock->setStock('3.000'); // Menor o igual a 5 para que salte la alerta

        $this->em->flush();

        $io->success('Datos de prueba generados correctamente. Ahora puedes verificar el Dashboard.');

        return Command::SUCCESS;
    }
}
