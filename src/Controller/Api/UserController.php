<?php

namespace App\Controller\Api;

use App\Entity\Branch;
use App\Entity\User;
use App\Entity\UserBranch;
use App\Form\Type\UserFormType;
use App\Repository\UserBranchRepository;
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
        $branch = $request->query->get('branch');
        $current = $request->query->getInt('current', 1);
        $pageSize = $request->query->getInt('pageSize', 10);


        $query = $userRepository->getWithPagination($search, $branch);
        $query->setFirstResult(($current - 1) * $pageSize)
            ->setMaxResults($pageSize);


        $paginator = new Paginator($query, true);
        $paginator->setUseOutputWalkers(false);

        return [
            'total' => count($paginator),
            'results' => $paginator->getIterator()->getArrayCopy(),
            'current' => $current,
            'pageSize' => $pageSize
        ];
    }

    #[Rest\Get('/user/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(Request $request, UserRepository $userRepository)
    {
        return $userRepository->getAllToSelect();
    }

    #[Rest\Get('/user/{id}', requirements: ['id' => '\d+'])]
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
    public function createUser(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): JsonResponse|User|FormInterface
    {
        $user = new User();
        $form = $this->createForm(UserFormType::class, $user);
        $form->handleRequest($request);

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


    #[Rest\Post('/user/{id}', requirements: ['id' => '\d+'])]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function updateUser(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, User $id): User|FormInterface
    {
        $user = $id;
        $form = $this->createForm(UserFormType::class, $user);
        $form->handleRequest($request);

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


    #[Rest\Delete('/user/{id}', requirements: ['id' => '\d+'])]
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


    #[Rest\Post('/user/lock/unlock/{id}', requirements: ['id' => '\d+'])]
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
        $branchId = $request->query->get('branch');
        error_log("[DEBUG] getBarbers called with branchId: " . ($branchId ?? 'NULL'));
        $result = $userRepository->findAllBarbers($excludeTimeOffToday, $branchId);
        error_log("[DEBUG] getBarbers returned " . count($result) . " records");
        return $result;
    }

    #[Rest\Get('/user/my-branches')]
    public function myBranches(Security $security, UserBranchRepository $userBranchRepository): JsonResponse
    {
        /** @var User $user */
        $user = $security->getUser();
        $rows = $userBranchRepository->findByUser($user->getId());

        $result = array_map(fn(UserBranch $ub) => [
            'id'        => $ub->getBranch()->getId(),
            'name'      => $ub->getBranch()->getName(),
            'isDefault' => $ub->isDefault(),
        ], $rows);

        return $this->json($result);
    }

    #[Rest\Get('/user/{id}/branches', requirements: ['id' => '\d+'])]
    public function getUserBranches(User $id, UserBranchRepository $userBranchRepository): JsonResponse
    {
        $rows = $userBranchRepository->findByUser($id->getId());

        $result = array_map(fn(UserBranch $ub) => [
            'id'        => $ub->getBranch()->getId(),
            'name'      => $ub->getBranch()->getName(),
            'isDefault' => $ub->isDefault(),
        ], $rows);

        return $this->json($result);
    }

    #[Rest\Post('/user/{id}/branches', requirements: ['id' => '\d+'])]
    public function assignBranches(
        User $id,
        Request $request,
        EntityManagerInterface $em,
        UserBranchRepository $userBranchRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $branches   = $data['branches']   ?? [];
        $defaultId  = $data['defaultId']  ?? null;

        // Eliminar asignaciones anteriores
        foreach ($userBranchRepository->findByUser($id->getId()) as $existing) {
            $em->remove($existing);
        }
        $em->flush();

        foreach ($branches as $branchId) {
            $branch = $em->getRepository(Branch::class)->find($branchId);
            if (!$branch) continue;

            $ub = new UserBranch();
            $ub->setUser($id);
            $ub->setBranch($branch);
            $ub->setIsDefault((int)$branchId === (int)$defaultId);
            $em->persist($ub);
        }
        $em->flush();

        return $this->json(['message' => 'Sucursales asignadas correctamente']);
    }
}
