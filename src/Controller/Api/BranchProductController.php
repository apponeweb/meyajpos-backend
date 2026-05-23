<?php

namespace App\Controller\Api;

use App\Entity\Branch;
use App\Entity\BranchProduct;
use App\Entity\MasterProduct;
use App\Repository\BranchProductRepository;
use App\Repository\MasterProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class BranchProductController extends AbstractFOSRestController
{
    #[Rest\Get('/branch/{id}/products', requirements: ['id' => '\d+'])]
    public function getProducts(
        Branch $id,
        Request $request,
        BranchProductRepository $branchProductRepository,
        MasterProductRepository $masterProductRepository
    ): JsonResponse {
        $current  = max(1, (int) $request->query->get('current', 1));
        $pageSize = max(1, (int) $request->query->get('pageSize', 10));
        $search   = trim($request->query->get('search', ''));

        $assigned = $branchProductRepository->findByBranch($id->getId());
        $assignedMap = [];
        foreach ($assigned as $bp) {
            $assignedMap[$bp->getProduct()->getId()] = $bp;
        }

        $qb = $masterProductRepository->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->orderBy('p.name', 'ASC');

        if ($search !== '') {
            $qb->andWhere('p.name LIKE :search OR p.sku LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy');
        $total = (int) $countQb
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $allProducts = $qb
            ->setFirstResult(($current - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        $results = array_map(function (MasterProduct $p) use ($assignedMap) {
            $bp = $assignedMap[$p->getId()] ?? null;
            return [
                'id'            => $p->getId(),
                'name'          => $p->getName(),
                'basePrice'     => (float) $p->getPrice(),
                'serviceType'   => $p->getServiceType()?->getName(),
                'assigned'      => $bp !== null,
                'enabled'       => $bp?->isEnabled() ?? false,
                'priceOverride' => $bp?->getPriceOverride() !== null ? (float) $bp->getPriceOverride() : null,
                'effectivePrice'=> $bp !== null ? $bp->getEffectivePrice() : (float) $p->getPrice(),
            ];
        }, $allProducts);

        return $this->json([
            'results'  => $results,
            'total'    => $total,
            'current'  => $current,
            'pageSize' => $pageSize,
        ]);
    }

    #[Rest\Post('/branch/{id}/products', requirements: ['id' => '\d+'])]
    public function saveProducts(
        Branch $id,
        Request $request,
        EntityManagerInterface $em,
        BranchProductRepository $branchProductRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $items = $data['products'] ?? [];

        foreach ($items as $item) {
            $productId   = (int) ($item['id'] ?? 0);
            $assigned    = (bool) ($item['assigned'] ?? false);
            $enabled     = (bool) ($item['enabled'] ?? true);
            $priceOverride = ($item['priceOverride'] ?? null) !== null && $item['priceOverride'] !== ''
                ? (string) $item['priceOverride']
                : null;

            $bp = $branchProductRepository->findOneByBranchAndProduct($id->getId(), $productId);

            if (!$assigned) {
                if ($bp) {
                    $em->remove($bp);
                }
                continue;
            }

            $product = $em->getRepository(MasterProduct::class)->find($productId);
            if (!$product) continue;

            if (!$bp) {
                $bp = new BranchProduct();
                $bp->setBranch($id);
                $bp->setProduct($product);
                $em->persist($bp);
            }

            $bp->setEnabled($enabled);
            $bp->setPriceOverride($priceOverride);
        }

        $em->flush();

        return $this->json(['message' => 'Productos de sucursal actualizados correctamente']);
    }
}
