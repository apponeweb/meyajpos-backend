<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @template T of object
 */
abstract class BaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * Retorna los campos básicos para el select.
     * Puede ser sobrescrito en el repositorio hijo.
     */
    protected function getDefaultFields(): array
    {
        return ['u.id', 'u.name', 'u.isActive'];
    }

    /**
     * Genera la query para paginación basada en NomenclatorTrait
     */
    public function getWithPagination($search = null): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('u')
            // Seleccionamos los campos exactos que quieres devolver
            ->select('u.id', 'u.name', 'u.description', 'u.isActive')
            ->where('u.deletedAt IS NULL')
            ->orderBy('u.id', 'ASC');

        if ($search) {
            $qb->andWhere('u.name LIKE :val OR u.description LIKE :val')
                ->setParameter('val', '%' . $search . '%');
        }

        return $qb->getQuery();
    }

    /**
     * Lista genérica para elementos de selección (Combos)
     */
    public function getAllToSelect(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id', 'u.name')
            ->where('u.isActive = :active')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('active', true)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
