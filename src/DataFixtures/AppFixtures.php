<?php

namespace App\DataFixtures;

use App\Entity\User;
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
        $user = new User();
        $user->setName('Administrador');
        $user->setEmail('admin@admin.com');
        $user->setRoles(['ROLE_ADMIN']);
        // hash the password (based on the security.yaml config for the $user class)
        $hashedPassword = $this->userPasswordHasher->hashPassword(
            $user,
            'admin'
        );

        $user->setPassword($hashedPassword);
        $manager->persist($user);
        $manager->flush();
    }
}
