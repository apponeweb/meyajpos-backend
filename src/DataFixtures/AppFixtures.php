<?php

namespace App\DataFixtures;

use App\Entity\Currency;
use App\Entity\PaymentType;
use App\Entity\ServiceType;
use App\Entity\User;
use App\Entity\Company;
use App\Entity\Branch;
use App\Entity\CashBox;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $userPasswordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Usuario
        $user = new User();
        $user->setName('Administrador');
        $user->setEmail('admin@admin.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, 'admin'));
        $manager->persist($user);

        // 2. Empresa
        $company = new Company();
        $company->setName('Corporativo Alpha');
        $company->setAcronym('ALPHA');
        $company->setLegalName('Alpha Systems S.A.');
        $company->setRfc('ALP123456XYZ');
        $manager->persist($company);

        $company1 = new Company();
        $company1->setName('Enterprise S.A.');
        $company1->setAcronym('ENTE');
        $company1->setLegalName('Enterprise S.A.');
        $company1->setRfc('ENT569456XYZ');
        $manager->persist($company1);

        // 3. Sucursales
        $branches = [];
        $branchNames = ['Sucursal Centro', 'Sucursal Poniente'];

        foreach ($branchNames as $index => $name) {
            $branch = new Branch();
            $branch->setName($name);
            $branch->setAcronym('BR' . ($index + 1));
            $branch->setAddress('Dirección conocida #' . ($index + 1));
            $branch->setCompany($company);
            $manager->persist($branch);
            $branches[] = $branch;
        }

        // 4. Cajas (CashBox) - Generamos 2 cajas por cada sucursal
        foreach ($branches as $branch) {
            for ($i = 1; $i <= 2; $i++) {
                $cashBox = new CashBox();
                // El campo 'name' viene del NomenclatorTrait
                $cashBox->setName("Caja " . $i . " - " . $branch->getName());
                $cashBox->setBranch($branch);
                $cashBox->setTicketSerie("SERIE-" . strtoupper(substr($branch->getName(), 0, 3)));
                $cashBox->setCurrentFolio(0); // Iniciamos el folio en cero

                $manager->persist($cashBox);
            }
        }

        $serviceType1 = new ServiceType();
        $serviceType1->setName('CORTE');
        $serviceType1->setDescription('CORTE');
        $manager->persist($serviceType1);

        $serviceType2 = new ServiceType();
        $serviceType2->setName('BARBA');
        $serviceType2->setDescription('BARBA');
        $manager->persist($serviceType2);

        $serviceType3 = new ServiceType();
        $serviceType3->setName('CEJA');
        $serviceType3->setDescription('CEJA');
        $manager->persist($serviceType2);

        $paymentType1 = new PaymentType();
        $paymentType1->setName('Transferencia');
        $paymentType1->setDescription('Transferencia');
        $paymentType1->setReferenceRequired(true);
        $manager->persist($paymentType1);

        $paymentType2 = new PaymentType();
        $paymentType2->setName('Tarjeta');
        $paymentType2->setDescription('Tarjeta');
        $paymentType2->setReferenceRequired(false);
        $manager->persist($paymentType2);

        $paymentType3 = new PaymentType();
        $paymentType3->setName('Efectivo');
        $paymentType3->setDescription('Efectivo');
        $paymentType3->setReferenceRequired(false);
        $paymentType3->setIsCash(true);
        $manager->persist($paymentType3);


        $currency = new Currency();
        $currency->setName('MX');
        $currency->setSymbol('MX');
        $currency->setCode('MX');
        $currency->setExchangeRate(0);
        $manager->persist($currency);


        $manager->flush();
    }
}
