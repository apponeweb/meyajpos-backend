<?php

namespace App\Controller\Api;

use App\Entity\BarberProfile;
use App\Entity\BarberSpecialty;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class BarberController extends BaseController
{
    protected function getEntityClass(): string
    {
        return User::class;
    }

    protected function getFormTypeClass(): string
    {
        return ''; // No form for this controller yet
    }

    #[Rest\Get('/barbers/export')]
    public function exportBarbersCsv(Request $request, UserRepository $userRepository): Response
    {
        try {
            $search = $request->query->get('search');
            $classification = $request->query->get('classification');
            $experience = $request->query->get('experience');

            $data = $userRepository->getBarbersWithPagination($search, $classification, $experience)->getQuery()->getResult();

            $response = new StreamedResponse(function () use ($data) {
                $handle = fopen('php://output', 'w+');
                // UTF-8 BOM for Excel
                fwrite($handle, "\xEF\xBB\xBF");

                // Headers
                fputcsv($handle, [
                    'ID',
                    'NOMBRE',
                    'APELLIDO',
                    'CORREO',
                    'TELÉFONO',
                    'ROL/CLASIFICACIÓN',
                    'EXPERIENCIA',
                    'RATING',
                    'RESEÑAS',
                    'ESTADO'
                ], ';');

                foreach ($data as $row) {
                    fputcsv($handle, [
                        $row['id'],
                        $row['name'],
                        $row['lastName'],
                        $row['email'],
                        $row['phone'],
                        $row['classification'] ?? '',
                        $row['experience'] ?? '',
                        number_format((float)($row['avgRating'] ?? 0), 2, '.', ''),
                        $row['ratingCount'] ?? 0,
                        ($row['enabled'] ?? true) ? 'Activo' : 'Inactivo'
                    ], ';');
                }
                fclose($handle);
            });

            $fileName = 'reporte_barberos_' . date('Ymd_His') . '.csv';
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

            return $response;

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al exportar barberos',
                'detail' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    #[Rest\Get('/barbers')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function getBarbersPaginated(Request $request, UserRepository $userRepository): JsonResponse
    {
        try {
            $search = $request->query->get('search');
            $classification = $request->query->get('classification');
            $experience = $request->query->get('experience');
            $current = $request->query->getInt('current', 1);
            $pageSize = $request->query->getInt('pageSize', 10);

            $qb = $userRepository->getBarbersWithPagination($search, $classification, $experience);

            // 1. Conteo eficiente (clonando el QueryBuilder)
            $countQb = clone $qb;
            $total = (int)$countQb->select('COUNT(DISTINCT u.id)')->getQuery()->getSingleScalarResult();

            // 2. Paginación y ejecución
            $qb->orderBy('u.id', 'ASC')
                ->setFirstResult(($current - 1) * $pageSize)
                ->setMaxResults($pageSize);

            return $this->json([
                'total' => $total,
                'results' => $qb->getQuery()->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY),
                'current' => $current,
                'pageSize' => $pageSize,
                'status' => Response::HTTP_OK
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'message' => 'Error al obtener el listado de barberos',
                'detail' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'status' => 500
            ], 500);
        }
    }


    #[Rest\Get('/barbers/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(UserRepository $userRepository)
    {
        return $userRepository->getAllBarbersToSelect();
    }

    #[Rest\Get('/barber/public-list')]
    public function publicList(Request $request, UserRepository $userRepository): JsonResponse
    {
        $qb = $userRepository->createQueryBuilder('u')
            ->select('u.id, u.name, u.lastName', 'p.photoUrl', 'p.avgRating', 'p.ratingCount', 'p.classification', 'p.experience')
            ->leftJoin(BarberProfile::class, 'p', 'WITH', 'p.user = u.id')
            ->where('u.barberSn = :isBarber')
            ->andWhere('u.enabled = :enabled')
            ->setParameter('isBarber', true)
            ->setParameter('enabled', true);

        $barbers = $qb->getQuery()->getResult();

        $scheme = $request->getScheme();
        $host = $request->getHttpHost();
        $baseUrl = $scheme . '://' . $host;

        $result = [];
        foreach ($barbers as $barber) {
            $specialties = $this->entityManager->getRepository(BarberSpecialty::class)
                ->createQueryBuilder('bs')
                ->select('s.name')
                ->join('bs.specialty', 's')
                ->where('bs.barber = :barberId')
                ->setParameter('barberId', $barber['id'])
                ->getQuery()
                ->getResult();

            $specialtiesList = array_map(fn($s) => $s['name'], $specialties);

            $result[] = [
                'id' => $barber['id'],
                'name' => $barber['name'] . ($barber['lastName'] ? ' ' . $barber['lastName'] : ''),
                'role' => $barber['classification'] ?? '',
                'experience' => $barber['experience'] . ' años de experiencia' ?? '',
                'rating' => (float)$barber['avgRating'],
                'reviewCount' => (int)$barber['ratingCount'],
                'image' => $barber['photoUrl'] ? $baseUrl . $barber['photoUrl'] : null,
                'specialties' => $specialtiesList,
            ];
        }

        return $this->json($result, Response::HTTP_OK);
    }
}
