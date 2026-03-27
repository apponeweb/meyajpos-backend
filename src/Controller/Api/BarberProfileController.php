<?php

namespace App\Controller\Api;

use App\Entity\BarberProfile;
use App\Form\Type\BarberProfileFormType;
use App\Repository\BarberProfileRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;

final class BarberProfileController extends BaseController
{
    protected function getEntityClass(): string
    {
        return BarberProfile::class;
    }

    protected function getFormTypeClass(): string
    {
        return BarberProfileFormType::class;
    }

    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.bio',
            'u.photoUrl',
            'u.avgRating',
            'u.ratingCount',
            'u.slotMinutes',
            'u.classification',
            'u.experience',
            'usr.id as userId',
            'usr.name as userName',
            'usr.lastName as userLastName'
        ];
    }

    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.user', 'usr');

        if ($userId = $request->query->get('userId')) {
            $qb->andWhere('usr.id = :userId')
                ->setParameter('userId', $userId);
        }
    }

    #[Rest\Get('/barber-profile')]
    public function index(Request $request, BarberProfileRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/barber-profile')]
    public function create(Request $request, BarberProfileRepository $repository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user'] ?? null;

        if ($userId) {
            $existing = $repository->findOneBy(['user' => $userId]);
            if ($existing) {
                // Si existe y tiene campos de borrado, lo reactivamos
                if (method_exists($existing, 'setDeletedAt')) $existing->setDeletedAt(null);
                if (method_exists($existing, 'setDeletedBy')) $existing->setDeletedBy(null);
                if (method_exists($existing, 'setIsActive')) $existing->setIsActive(true);

                return $this->handleSave($request, $existing);
            }
        }

        return $this->handleSave($request, new BarberProfile());
    }

    #[Rest\Put('/barber-profile/{userId}')]
    public function update(Request $request, int $userId, BarberProfileRepository $repository): JsonResponse
    {
        $id = $repository->findOneBy(['user' => $userId]);
        if (!$id) {
            return $this->json(['message' => 'Profile not found'], Response::HTTP_NOT_FOUND);
        }
        return $this->handleSave($request, $id);
    }

    #[Rest\Delete('/barber-profile/{userId}')]
    public function remove(int $userId, BarberProfileRepository $repository): mixed
    {
        $id = $repository->findOneBy(['user' => $userId]);
        if (!$id) {
            return $this->json(['message' => 'Profile not found'], Response::HTTP_NOT_FOUND);
        }
        return $this->delete($id);
    }

    #[Rest\Get('/barber-profile/{userId}')]
    public function getProfile(int $userId, BarberProfileRepository $repository): JsonResponse
    {
        $qb = $repository->createQueryBuilder('u')
            ->select($this->getListSelectFields())
            ->leftJoin('u.user', 'usr')
            ->where('usr.id = :userId')
            ->setParameter('userId', $userId)
            ->andWhere('u.deletedAt IS NULL');

        $result = $qb->getQuery()->getOneOrNullResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        if (!$result) {
            return $this->json(['message' => 'Profile not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result, Response::HTTP_OK);
    }

    private function handleSave(Request $request, BarberProfile $entity): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Handle photo base64
        if (isset($data['photoBase64']) && !empty($data['photoBase64'])) {
            $base64 = $data['photoBase64'];
            $fileUrl = $this->saveBase64Image($base64);
            if ($fileUrl) {
                $entity->setPhotoUrl($fileUrl);
            }
        } elseif (isset($data['removePhoto']) && $data['removePhoto'] === true) {
            $entity->setPhotoUrl(null);
        }

        // We override the request content if we need to let the base form handle it
        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            json_encode($data)
        );

        $isNew = $entity->getUser() === null;

        $response = $this->processForm($request, $entity, "Perfil del barbero guardado correctamente");

        if ($response->getStatusCode() === Response::HTTP_OK && $isNew) {
            $now = new \DateTime();
            $userSession = $this->security->getUser();
            $userSessionId = ($userSession && method_exists($userSession, 'getId')) ? (int) $userSession->getId() : null;

            $entity->setCreatedAt($now);
            if ($userSessionId)
                $entity->setCreatedBy($userSessionId);

            $this->entityManager->flush();
        }

        return $response;
    }

    private function saveBase64Image(string $base64String): ?string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            $data = substr($base64String, strpos($base64String, ',') + 1);
            $type = strtolower($type[1]);

            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return null;
            }

            $data = base64_decode($data);

            if ($data === false) {
                return null;
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/barbers';
            $fs = new Filesystem();
            if (!$fs->exists($uploadDir)) {
                $fs->mkdir($uploadDir, 0755);
            }

            $fileName = uniqid('barber_') . '.' . $type;
            $filePath = $uploadDir . '/' . $fileName;

            file_put_contents($filePath, $data);

            // Host will be prepended by frontend or in request
            return '/uploads/barbers/' . $fileName;
        }

        return null;
    }
}
