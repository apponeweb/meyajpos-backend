<?php

namespace App\Controller\Api;

use App\Repository\UserRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;

final class BarberController extends AbstractFOSRestController
{
    #[Rest\Get('/barbers')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function getBarbersPaginated(Request $request, UserRepository $userRepository): array
    {
        $search = $request->query->get('search');
        $current = $request->query->getInt('current', 1);
        $pageSize = $request->query->getInt('pageSize', 10);

        $query = $userRepository->getBarbersWithPagination($search);

        $query->setFirstResult(($current - 1) * $pageSize)
            ->setMaxResults($pageSize);

        // Importante: Al usar un SELECT parcial, count() del paginator funciona sobre los resultados escalares
        $paginator = new Paginator($query, true);
        $paginator->setUseOutputWalkers(false);
        return [
            'total' => count($paginator),
            'results' => $paginator->getIterator()->getArrayCopy(),
            'current' => $current,
            'pageSize' => $pageSize
        ];
    }


    #[Rest\Get('/barbers/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(UserRepository $userRepository)
    {
        return $userRepository->getAllBarbersToSelect();
    }
}
