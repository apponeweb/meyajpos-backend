<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class LicenseProvisionController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $provisionToken,
        private readonly string $defaultPassword,
        private readonly string $defaultRole,
    ) {}

    #[Route('/license/provision', name: 'api_license_provision', methods: ['POST'])]
    public function provision(Request $request): JsonResponse
    {
        // 1. Verificar token estático
        $incoming = $request->headers->get('X-License-Token', '');
        if ($this->provisionToken === '' || !hash_equals($this->provisionToken, $incoming)) {
            return $this->json(['message' => 'No autorizado'], 401);
        }

        // 2. Decodificar JSON
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Cuerpo JSON inválido o vacío'], 400);
        }

        // 3. Validar email
        $email = trim($payload['email'] ?? '');
        if ($email === '') {
            return $this->json(['message' => 'El campo email es obligatorio'], 400);
        }

        // 4. Usuario ya existe
        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            return $this->json(['message' => 'Usuario ya existe', 'created' => false], 200);
        }

        // 5. Determinar nombre
        $companyName = trim($payload['companyName'] ?? '');
        $name = $companyName !== '' ? $companyName : $email;

        // 6. Crear usuario
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setRoles([$this->defaultRole]);
        $user->setEnabled(true);
        $user->setBarberSn(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, $this->defaultPassword));

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            return $this->json(['message' => 'Error al guardar el usuario: ' . $e->getMessage()], 500);
        }

        return $this->json(['message' => 'Usuario creado', 'created' => true], 201);
    }
}
