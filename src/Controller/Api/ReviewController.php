<?php

namespace App\Controller\Api;

use App\Entity\BarberProfile;
use App\Entity\Branch;
use App\Entity\Review;
use App\Entity\User;
use App\Form\Type\ReviewFormType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReviewController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Review::class;
    }

    protected function getFormTypeClass(): string
    {
        return ReviewFormType::class;
    }

    protected function getSearchFields(): array
    {
        return ['u.customerName', 'u.comment'];
    }

    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        $qb->leftJoin('u.branch', 'b')
            ->leftJoin('u.barber', 'bar')
            ->leftJoin('bar.profile', 'bp');

        if ($barberId = $request->query->get('barberId')) {
            $qb->andWhere('bar.id = :barberId')
                ->setParameter('barberId', $barberId);
        }

        if ($branchId = $request->query->get('branchId')) {
            $qb->andWhere('b.id = :branchId')
                ->setParameter('branchId', $branchId);
        }

        if ($rating = $request->query->get('rating')) {
            $qb->andWhere('u.rating = :rating')
                ->setParameter('rating', $rating);
        }

        if ($dateFrom = $request->query->get('dateFrom')) {
            $qb->andWhere('u.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $dateFrom . ' 00:00:00');
        }

        if ($dateTo = $request->query->get('dateTo')) {
            $qb->andWhere('u.createdAt <= :dateTo')
                ->setParameter('dateTo', $dateTo . ' 23:59:59');
        }
    }

    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.customerName',
            'u.rating',
            'u.comment',
            'u.createdAt',
            'u.isActive',
            'b.id as branchId',
            'b.name as branchName',
            'bar.id as barberId',
            'bar.name as barberName',
            'bp.photoUrl as barberPhoto',
        ];
    }

    // ──────────────── CRUD ENDPOINTS ────────────────

    #[Rest\Get('/review')]
    public function index(Request $request, ReviewRepository $repository): JsonResponse
    {
        $response = $this->list($request, $repository);
        $data = json_decode($response->getContent(), true);
        $baseUrl = $request->getSchemeAndHttpHost();

        if (isset($data['results'])) {
            $data['results'] = array_map(function ($item) use ($baseUrl) {
                $item['barberPhoto'] = !empty($item['barberPhoto'])
                    ? $baseUrl . $item['barberPhoto']
                    : null;
                return $item;
            }, $data['results']);
        }

        return new JsonResponse($data, $response->getStatusCode());
    }

    #[Rest\Get('/review/{id}', requirements: ['id' => '\d+'])]
    public function get(Review $id): JsonResponse
    {
        if ($id->getDeletedAt() !== null || !$id->isActive()) {
            return $this->json(['message' => 'El registro no está disponible o ha sido eliminado'], Response::HTTP_NOT_FOUND);
        }

        $branch = $id->getBranch();
        $barber = $id->getBarber();

        return $this->json([
            'id' => $id->getId(),
            'customerName' => $id->getCustomerName(),
            'rating' => $id->getRating(),
            'comment' => $id->getComment(),
            'isActive' => $id->isActive(),
            'branch' => $branch ? ['id' => $branch->getId(), 'name' => $branch->getName()] : null,
            'barber' => $barber ? ['id' => $barber->getId(), 'name' => $barber->getName()] : null,
            'createdAt' => $id->getCreatedAt()?->format('d/m/Y H:i:s'),
        ], Response::HTTP_OK);
    }

    #[Rest\Post('/review')]
    public function create(Request $request): JsonResponse
    {
        $review = new Review();
        $response = $this->processForm($request, $review, "Reseña creada correctamente");

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $this->updateRatings($review);
        }

        return $response;
    }

    #[Rest\Put('/review/{id}')]
    public function update(Request $request, Review $id): JsonResponse
    {
        $oldRating = $id->getRating();
        $oldBranch = $id->getBranch();
        $oldBarber = $id->getBarber();

        $response = $this->processForm($request, $id, "Reseña actualizada correctamente");

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $this->recalculateRatings($id, $oldRating, $oldBranch, $oldBarber);
        }

        return $response;
    }

    #[Rest\Delete('/review/{id}')]
    public function remove(Review $id): JsonResponse
    {
        $branch = $id->getBranch();
        $barber = $id->getBarber();
        $rating = $id->getRating();

        $response = $this->delete($id);

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $this->removeFromRatings($rating, $branch, $barber);
        }

        return $response;
    }

    // ──────────────── PUBLIC ENDPOINTS (LANDING) ────────────────

    /**
     * POST /api/review/rate-branch
     */
    #[Rest\Post('/review/rate-branch')]
    public function rateBranch(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $branchId = $data['branchId'] ?? null;
        $rating = $data['rating'] ?? null;

        if (!$branchId || !$rating || $rating < 1 || $rating > 5) {
            return $this->json(['message' => 'branchId y rating (1-5) son requeridos'], Response::HTTP_BAD_REQUEST);
        }

        $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
        if (!$branch) {
            return $this->json(['message' => 'Sucursal no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $currentRating = (float) ($branch->getRating() ?? 0);
        $currentCount = (int) ($branch->getReviewCount() ?? 0);

        $newCount = $currentCount + 1;
        $newRating = (($currentRating * $currentCount) + $rating) / $newCount;

        $branch->setRating(round($newRating, 2));
        $branch->setReviewCount($newCount);

        $this->entityManager->flush();

        return $this->json([
            'rating' => round($newRating, 2),
            'reviewCount' => $newCount,
        ]);
    }

    /**
     * POST /api/review/rate-barber
     */
    #[Rest\Post('/review/rate-barber')]
    public function rateBarber(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $barberId = $data['barberId'] ?? null;
        $rating = $data['rating'] ?? null;

        if (!$barberId || !$rating || $rating < 1 || $rating > 5) {
            return $this->json(['message' => 'barberId y rating (1-5) son requeridos'], Response::HTTP_BAD_REQUEST);
        }

        $profile = $this->entityManager->getRepository(BarberProfile::class)
            ->findOneBy(['user' => $barberId]);

        if (!$profile) {
            return $this->json(['message' => 'Barbero no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $currentRating = (float) ($profile->getAvgRating() ?? 0);
        $currentCount = $profile->getRatingCount();

        $newCount = $currentCount + 1;
        $newRating = (($currentRating * $currentCount) + $rating) / $newCount;

        $profile->setAvgRating(number_format($newRating, 2, '.', ''));
        $profile->setRatingCount($newCount);

        $this->entityManager->flush();

        return $this->json([
            'rating' => round($newRating, 2),
            'ratingCount' => $newCount,
        ]);
    }

    /**
     * POST /api/review/submit
     */
    #[Rest\Post('/review/submit')]
    public function submitReview(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $customerName = trim($data['customerName'] ?? '');
        $rating = $data['rating'] ?? null;
        $comment = trim($data['comment'] ?? '');
        $branchId = $data['branchId'] ?? null;
        $barberId = $data['barberId'] ?? null;

        if (!$customerName || !$rating || !$comment || $rating < 1 || $rating > 5) {
            return $this->json(['message' => 'customerName, rating (1-5) y comment son requeridos'], Response::HTTP_BAD_REQUEST);
        }

        $branch = null;
        if ($branchId) {
            $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
        }

        $barber = null;
        if ($barberId) {
            $barber = $this->entityManager->getRepository(User::class)->find($barberId);
        }

        $review = new Review();
        $review->setCustomerName($customerName);
        $review->setRating((int) $rating);
        $review->setComment($comment);
        $review->setBranch($branch);
        $review->setBarber($barber);

        $this->entityManager->persist($review);

        if ($branch) {
            $currentRating = (float) ($branch->getRating() ?? 0);
            $currentCount = (int) ($branch->getReviewCount() ?? 0);
            $newCount = $currentCount + 1;
            $newRating = (($currentRating * $currentCount) + $rating) / $newCount;
            $branch->setRating(round($newRating, 2));
            $branch->setReviewCount($newCount);
        }

        if ($barber) {
            $profile = $this->entityManager->getRepository(BarberProfile::class)
                ->findOneBy(['user' => $barber->getId()]);
            if ($profile) {
                $currentRating = (float) ($profile->getAvgRating() ?? 0);
                $currentCount = $profile->getRatingCount();
                $newCount = $currentCount + 1;
                $newRating = (($currentRating * $currentCount) + $rating) / $newCount;
                $profile->setAvgRating(number_format($newRating, 2, '.', ''));
                $profile->setRatingCount($newCount);
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $review->getId(),
            'customerName' => $review->getCustomerName(),
            'rating' => $review->getRating(),
            'comment' => $review->getComment(),
            'branch' => $branch ? $branch->getName() : null,
            'barber' => $barber ? $barber->getName() : null,
            'createdAt' => $review->getCreatedAt()->format('Y-m-d'),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/review/public-list
     */
    #[Rest\Get('/review/public-list')]
    public function publicList(Request $request): JsonResponse
    {
        $branchId = $request->query->get('branchId');

        $qb = $this->entityManager->getRepository(Review::class)->createQueryBuilder('r')
            ->select('r.id', 'r.customerName', 'r.rating', 'r.comment', 'r.createdAt')
            ->addSelect('b.name as branchName')
            ->addSelect('u.name as barberName')
            ->leftJoin('r.branch', 'b')
            ->leftJoin('r.barber', 'u')
            ->where('r.deletedAt IS NULL')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(50);

        if ($branchId) {
            $qb->andWhere('r.branch = :branchId')
                ->setParameter('branchId', $branchId);
        }

        $reviews = $qb->getQuery()->getResult();

        $result = array_map(fn($r) => [
            'id' => $r['id'],
            'name' => $r['customerName'],
            'rating' => (int) $r['rating'],
            'comment' => $r['comment'],
            'date' => $r['createdAt']->format('Y-m-d'),
            'branch' => $r['branchName'] ?? null,
            'barber' => $r['barberName'] ?? '—',
            'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($r['customerName']) . '&background=c9a96e&color=fff&size=80',
        ], $reviews);

        $totalReviews = count($result);
        $avgRating = $totalReviews > 0
            ? round(array_sum(array_column($result, 'rating')) / $totalReviews, 1)
            : 0;

        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($result as $r) {
            $breakdown[$r['rating']]++;
        }

        return $this->json([
            'reviews' => $result,
            'summary' => [
                'average' => $avgRating,
                'total' => $totalReviews,
                'breakdown' => $breakdown,
            ],
        ]);
    }

    // ──────────────── PRIVATE HELPERS ────────────────

    private function updateRatings(Review $review): void
    {
        $branch = $review->getBranch();
        $barber = $review->getBarber();
        $rating = $review->getRating();

        if ($branch) {
            $currentRating = (float) ($branch->getRating() ?? 0);
            $currentCount = (int) ($branch->getReviewCount() ?? 0);
            $newCount = $currentCount + 1;
            $newRating = (($currentRating * $currentCount) + $rating) / $newCount;
            $branch->setRating(round($newRating, 2));
            $branch->setReviewCount($newCount);
        }

        if ($barber) {
            $profile = $this->entityManager->getRepository(BarberProfile::class)
                ->findOneBy(['user' => $barber->getId()]);
            if ($profile) {
                $currentRating = (float) ($profile->getAvgRating() ?? 0);
                $currentCount = $profile->getRatingCount();
                $newCount = $currentCount + 1;
                $newRating = (($currentRating * $currentCount) + $rating) / $newCount;
                $profile->setAvgRating(number_format($newRating, 2, '.', ''));
                $profile->setRatingCount($newCount);
            }
        }

        $this->entityManager->flush();
    }

    private function recalculateRatings(Review $review, int $oldRating, ?Branch $oldBranch, ?User $oldBarber): void
    {
        // Remove old rating from old entities
        $this->removeFromRatings($oldRating, $oldBranch, $oldBarber);

        // Add new rating to current entities
        $this->updateRatings($review);
    }

    private function removeFromRatings(int $rating, ?Branch $branch, ?User $barber): void
    {
        if ($branch) {
            $currentRating = (float) ($branch->getRating() ?? 0);
            $currentCount = (int) ($branch->getReviewCount() ?? 0);

            if ($currentCount > 1) {
                $newCount = $currentCount - 1;
                $newRating = (($currentRating * $currentCount) - $rating) / $newCount;
                $branch->setRating(round($newRating, 2));
                $branch->setReviewCount($newCount);
            } else {
                $branch->setRating(0);
                $branch->setReviewCount(0);
            }
        }

        if ($barber) {
            $profile = $this->entityManager->getRepository(BarberProfile::class)
                ->findOneBy(['user' => $barber->getId()]);
            if ($profile) {
                $currentRating = (float) ($profile->getAvgRating() ?? 0);
                $currentCount = $profile->getRatingCount();

                if ($currentCount > 1) {
                    $newCount = $currentCount - 1;
                    $newRating = (($currentRating * $currentCount) - $rating) / $newCount;
                    $profile->setAvgRating(number_format($newRating, 2, '.', ''));
                    $profile->setRatingCount($newCount);
                } else {
                    $profile->setAvgRating('0.00');
                    $profile->setRatingCount(0);
                }
            }
        }

        $this->entityManager->flush();
    }
}
