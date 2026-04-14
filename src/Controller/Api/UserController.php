<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Form\Type\UserFormType;
use App\License\Service\LicenseValidatorService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\FormInterface;

final class UserController extends AbstractFOSRestController
{
    #[Rest\Get('/user')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function index(Request $request, UserRepository $userRepository): array
    {
        $search = $request->query->get('search');
        $current = $request->query->getInt('current', 1);
        $pageSize = $request->query->getInt('pageSize', 10);


        $query = $userRepository->getWithPagination($search);
        $query->setFirstResult(($current - 1) * $pageSize)
            ->setMaxResults($pageSize);


        $paginator = new Paginator($query, true);
        $paginator->setUseOutputWalkers(false);

        return [
            'total' => count($paginator),
            'results' => $paginator->getIterator()->getArrayCopy(),
            'current' => $current,
            'pageSize' => $pageSize,
            'barberCount' => $userRepository->countBarbers()
        ];
    }

    #[Rest\Get('/user/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(Request $request, UserRepository $userRepository)
    {
        return $userRepository->getAllToSelect();
    }

    #[Rest\Get('/user/{id}')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function getUserById(User $id): User
    {
        return $id;
    }


    #[Rest\Post('/user/information')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function getUserByEmail(Request $request, UserRepository $userRepository): User|null
    {

        $jsonParams = json_decode($request->getContent(), true);
        return $userRepository->findOneBy(['email' => $jsonParams['email']]);
    }


    #[Rest\Post('/user')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function createUser(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, LicenseValidatorService $licenseValidator, Security $security): JsonResponse|User|FormInterface
    {
        // Validar límite de barberos si el nuevo usuario será barbero
        $data = json_decode($request->getContent(), true) ?? [];
        $isBarber = isset($data['barberSn']) && $data['barberSn'] === true;

        if ($isBarber) {
            $currentUser = $security->getUser();
            if ($currentUser instanceof User) {
                $check = $licenseValidator->canCreateBarber($currentUser);
                if (!$check['allowed']) {
                    return $this->json(['message' => $check['reason']], 400);
                }
            }
        }

        $user = new User();
        $form = $this->createForm(UserFormType::class, $user);
        $form->submit($data);

        $exist = $userRepository->findOneBy(['email' => $user->getEmail()]);
        if (!empty($exist)) {
            $form->get('email')->addError(new FormError('Ya existe un usuario registrado con este correo'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $user->getPassword()
            );
            $user->setPassword($hashedPassword);
            $entityManager->persist($user);
            $entityManager->flush();
            return $user;
        }
        return $form;
    }


    #[Rest\Post('/user/{id}')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function updateUser(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, User $id): User|FormInterface
    {
        $user = $id;
        $form = $this->createForm(UserFormType::class, $user);
        $data = json_decode($request->getContent(), true) ?? [];
        $form->submit($data, false);

        $exist = $userRepository->findOneBy(['email' => $user->getEmail()]);
        if (!empty($exist) && $exist->getId() != $user->getId()) {
            $form->get('email')->addError(new FormError('Ya existe un usuario registrado con este correo'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();
            return $user;
        }
        return $form;
    }


    #[Rest\Delete('/user/{id}')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function removeUser(EntityManagerInterface $entityManager, User $id): JsonResponse
    {
        try {
            $entityManager->remove($id);
            $entityManager->flush();
            return $this->json(['message' => "Usuario eliminado"]);
        } catch (\Exception $e) {
            return $this->json(['message' => "Error al eliminar usuario"], 400);
        }
    }


    #[Rest\Post('/user/lock/unlock/{id}')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function userLockUnlock(EntityManagerInterface $entityManager, User $id): JsonResponse
    {
        $id->setEnabled(!$id->isEnabled());
        $entityManager->flush();
        return $this->json(['message' => "Bloqueo o desbloqueo exitoso"]);
    }


    #[Rest\Post('/change/password/{id}')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function changePassword(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, User $id)
    {
        $data = json_decode($request->getContent(), true);
        $hashedPassword = $passwordHasher->hashPassword(
            $id,
            $data['password']
        );
        $id->setPassword($hashedPassword);
        $entityManager->flush();
        return $this->json(['message' => "La contraseña ha sido cambiada exitosamente."], 200);
    }

    #[Rest\Get('/user/list/barbers')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function getBarbers(Request $request, UserRepository $userRepository): array
    {
        $excludeTimeOffToday = $request->query->getBoolean('excludeTimeOffToday', false);
        return $userRepository->findAllBarbers($excludeTimeOffToday);
    }
}
