<?php

namespace App\Controller\Api;

use App\Entity\BarberProfile;
use App\Entity\Branch;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReviewController extends AbstractFOSRestController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * POST /api/review/rate-branch
     * Body: { "branchId": 3, "rating": 5 }
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
     * Body: { "barberId": 5, "rating": 4 }
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
     * Body: { "customerName": "Andrés M.", "rating": 5, "comment": "...", "branchId": 3, "barberId": 5 }
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

        // Update branch rating
        if ($branch) {
            $currentRating = (float) ($branch->getRating() ?? 0);
            $currentCount = (int) ($branch->getReviewCount() ?? 0);
            $newCount = $currentCount + 1;
            $newRating = (($currentRating * $currentCount) + $rating) / $newCount;
            $branch->setRating(round($newRating, 2));
            $branch->setReviewCount($newCount);
        }

        // Update barber rating
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
     * GET /api/review/public-list?branchId=3
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
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(50);

        if ($branchId) {
            $qb->where('r.branch = :branchId')
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

        // Rating summary
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
}
