<?php

namespace App\Controller\Api;

use App\Entity\Sale;
use App\Repository\BaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController extends AbstractFOSRestController
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected Security               $security
    )
    {
    }

    abstract protected function getEntityClass(): string;

    abstract protected function getFormTypeClass(): string;

    protected function getSearchFields(): array
    {
        return ['u.name', 'u.description'];
    }
    /**
     * Lista paginada genérica
     */
    protected function list(Request $request, BaseRepository $repository): JsonResponse
    {
        $search = $request->query->get('search');
        $current = $request->query->getInt('current', 1);
        $pageSize = $request->query->getInt('pageSize', 10);

        $qb = $repository->createQueryBuilder('u')
            ->where('u.deletedAt IS NULL');

        // --- HOOK 1: JOINS Y FILTROS PERSONALIZADOS ---
        $this->configureListQuery($qb, $request);

        if ($search) {
            $searchFields = $this->getSearchFields();
            $orStatements = $qb->expr()->orX();

            foreach ($searchFields as $field) {
                $orStatements->add($qb->expr()->like($field, ':val'));
            }

            $qb->andWhere($orStatements)
                ->setParameter('val', '%' . $search . '%');
        }

        // 2. Conteo eficiente
        $countQb = clone $qb;
        $totalItems = (int)$countQb->select('COUNT(DISTINCT u.id)')->getQuery()->getSingleScalarResult();

        // --- HOOK 2: SELECT PERSONALIZADO ---
        // Si el hijo no define campos, usamos los básicos por defecto
        $selectFields = $this->getListSelectFields();
        $qb->select($selectFields);

        $qb->orderBy('u.id', 'ASC')
            ->setFirstResult(($current - 1) * $pageSize)
            ->setMaxResults($pageSize);

        $results = $qb->getQuery()->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        return $this->json([
            'total' => $totalItems,
            'results' => $results,
            'current' => $current,
            'pageSize' => $pageSize
        ], Response::HTTP_OK);
    }

// Métodos por defecto (se pueden sobrescribir en los hijos)
    protected function configureListQuery(\Doctrine\ORM\QueryBuilder $qb, Request $request): void
    {
    }

    protected function getListSelectFields(): array
    {
        return ['u.id', 'u.name', 'u.description', 'u.isActive'];
    }

    protected function processForm(Request $request, object $entity, string $successMessage = "Operación realizada con éxito"): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;

        // --- LÓGICA DE RE-ACTIVACIÓN ---
        if (null === $entity->getId() && $name !== null) {
            $repository = $this->entityManager->getRepository($this->getEntityClass());

            // Buscamos si existe uno con el mismo nombre (incluyendo desactivados/borrados)
            // Usamos findOneBy porque 'name' es único según tu Trait
            $existingEntity = $repository->findOneBy(['name' => $name]);

            if ($existingEntity) {
                // Si el registro existe y está marcado como borrado o inactivo
                if (method_exists($existingEntity, 'getDeletedAt') && $existingEntity->getDeletedAt() !== null ||
                    method_exists($existingEntity, 'isActive') && !$existingEntity->isActive()) {

                    $entity = $existingEntity; // Cambiamos la entidad nueva por la existente

                    // Limpiamos los campos de borrado
                    if (method_exists($entity, 'setDeletedAt')) $entity->setDeletedAt(null);
                    if (method_exists($entity, 'setDeletedBy')) $entity->setDeletedBy(null);
                    if (method_exists($entity, 'setIsActive')) $entity->setIsActive(true);

                    $successMessage = "El registro ya existía y ha sido reactivado correctamente.";
                }
            }
        }
        // -------------------------------

        $form = $this->createForm($this->getFormTypeClass(), $entity);
        $form->submit($data ?? $request->request->all(), $request->getMethod() !== 'PATCH');

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $now = new \DateTime();
                $user = $this->security->getUser();
                $userId = ($user && method_exists($user, 'getId')) ? (int)$user->getId() : null;

                if ($entity instanceof Sale) {
                    // 1. Asignar la fecha actual como fecha de venta
                    if (method_exists($entity, 'setSaleDate')) {
                        $entity->setSaleDate($now);
                    }
                    // 2. Asignar el usuario autenticado como el vendedor/usuario de la venta
                    if ($user && method_exists($entity, 'setUser')) {
                        $entity->setUser($user);
                    }
                    if (null === $entity->getId()) { // Solo si es una creación nueva
                        $newFolio = $this->generateDailyFolio($now);
                        $entity->setFolio($newFolio);
                    }
                }

                if (null === $entity->getId()) {
                    if (method_exists($entity, 'setCreatedAt')) $entity->setCreatedAt($now);
                    if ($userId && method_exists($entity, 'setCreatedBy')) $entity->setCreatedBy($userId);
                }

                if (method_exists($entity, 'setUpdatedAt')) $entity->setUpdatedAt($now);
                if ($userId && method_exists($entity, 'setUpdatedBy')) $entity->setUpdatedBy($userId);

                $this->entityManager->persist($entity);
                $this->entityManager->flush();

                return $this->json([
                    'message' => $successMessage,
                    'data' => ['id' => $entity->getId()]
                ], Response::HTTP_OK);

            } catch (\Exception $e) {
                return $this->json(['message' => 'Error: ' . $e->getMessage()], 500);
            }
        }

        return $this->json([
            'message' => 'Validación fallida',
            'errors' => $this->getFormErrors($form)
        ], Response::HTTP_BAD_REQUEST);
    }

    private function generateDailyFolio(\DateTime $date): string
    {
        $repository = $this->entityManager->getRepository(Sale::class);

        // Definir el rango del día (desde las 00:00:00 hasta las 23:59:59)
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        // Contar cuántas ventas se han realizado hoy
        $count = $repository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.saleDate BETWEEN :start AND :end')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        $nextNumber = (int)$count + 1;

        // Formato: AÑO-MES-DIA-CONSECUTIVO (con ceros a la izquierda)
        // Ejemplo: 20260126-0001
        return sprintf('%s-%04d', $date->format('Ymd'), $nextNumber);
    }
    /**
     * Helper para extraer errores del formulario de forma legible
     */
    protected function getFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [
            'children' => []
        ];

        // 1. Recorrer cada campo del formulario (children)
        foreach ($form->all() as $child) {
            $childErrors = [];

            // 2. Extraer los mensajes de error de este campo específico
            foreach ($child->getErrors() as $error) {
                $childErrors[] = $error->getMessage();
            }

            // 3. Solo agregar al JSON si el campo realmente tiene errores
            if (!empty($childErrors)) {
                $errors['children'][$child->getName()] = [
                    'errors' => $childErrors
                ];
            }
        }

        // 4. (Opcional) Errores globales del formulario (no asociados a un campo)
        $formErrors = [];
        foreach ($form->getErrors() as $error) {
            $formErrors[] = $error->getMessage();
        }

        if (!empty($formErrors)) {
            $errors['errors'] = $formErrors;
        }

        return $errors;
    }

    public function delete(object $id): JsonResponse
    {
        try {
            // 1. Obtener usuario autenticado
            $user = $this->security->getUser();
            $userId = ($user && method_exists($user, 'getId')) ? (int)$user->getId() : null;
            $now = new \DateTime();

            // 2. Asignar valores de borrado lógico (Campos de BaseEntity)
            if (method_exists($id, 'setDeletedAt')) {
                $id->setDeletedAt($now);
            }

            if ($userId && method_exists($id, 'setDeletedBy')) {
                $id->setDeletedBy($userId);
            }

            if (method_exists($id, 'setIsActive')) {
                $id->setIsActive(false);
            }

            // 3. Persistir los cambios en lugar de eliminar físicamente
            // IMPORTANTE: Usamos persist() + flush(), NO remove()
            $this->entityManager->persist($id);
            $this->entityManager->flush();

            return $this->json([
                'message' => "Registro desactivado y marcado como eliminado correctamente"
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'message' => "Error al intentar eliminar el registro",
                'detail' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function getDetails(object $entity): JsonResponse
    {
        try {
            $isDeleted = method_exists($entity, 'getDeletedAt') && $entity->getDeletedAt() !== null;
            $isInactive = method_exists($entity, 'getIsActive') && $entity->getIsActive() === false;

            if ($isDeleted || $isInactive) {
                return $this->json([
                    'message' => "El registro no está disponible o ha sido eliminado"
                ], Response::HTTP_NOT_FOUND);
            }
            return $this->json($entity, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'message' => "Error al obtener los detalles del registro",
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function normalizeAddress(Request $request, $field): void
    {
        $content = json_decode($request->getContent(), true);

        if (isset($content[$field]) && is_array($content[$field])) {
            $content[$field] = json_encode($content[$field]);

            // Reinicializamos el Request con el nuevo contenido serializado
            $request->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                json_encode($content)
            );
        }
    }
}
