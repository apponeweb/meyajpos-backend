<?php

namespace App\Controller\Api;

use App\Entity\PaymentType;
use App\Entity\Sale;
use App\Entity\SalePayment;
use App\Entity\Tip;
use App\Form\Type\SaleFormType;
use App\Repository\SaleRepository;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class SaleController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Sale::class;
    }

    protected function getFormTypeClass(): string
    {
        return SaleFormType::class;
    }

    /**
     * Configuramos los joins para obtener información de la Caja y el Usuario
     */
    protected function configureListQuery(QueryBuilder $qb, Request $request): void
    {
        // 'u' es la entidad Sale
        $qb->leftJoin('u.cashBox', 'cb')
            ->leftJoin('u.user', 'usr');

        // Filtro por Folio
        if ($folio = $request->query->get('folio')) {
            $qb->andWhere('u.folio LIKE :folio')
                ->setParameter('folio', '%' . $folio . '%');
        }

        // Filtro por Estado (Enum value)
        if ($status = $request->query->get('status')) {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', $status);
        }

        // Filtro por Rango de Fechas
        if ($startDate = $request->query->get('startDate')) {
            $qb->andWhere('u.dateTime >= :start')
                ->setParameter('start', new \DateTime($startDate));
        }
        if ($endDate = $request->query->get('endDate')) {
            $qb->andWhere('u.dateTime <= :end')
                ->setParameter('end', new \DateTime($endDate . ' 23:59:59'));
        }
    }

    /**
     * Definimos los campos de la venta, incluyendo el total y datos de relaciones
     */
    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.folio',
            'u.saleDate',
            'u.subtotal',
            'u.totalTax',
            'u.total',
            'u.status', // Doctrine serializará el valor prestablecido del Enum
            'cb.id as cashBoxId',
            'cb.name as cashBoxName',
            'usr.id as userId',
            'usr.name as userName'
        ];
    }

    #[Rest\Get('/sale')]
    public function index(Request $request, SaleRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/sale')]
    public function create(Request $request, \App\Repository\CashBoxSessionRepository $sessionRepo): JsonResponse
    {
        $user = $this->security->getUser();

        // 1. Validar sesión abierta
        $activeSession = $sessionRepo->findOneBy([
            'user' => $user,
            'status' => \App\Enum\CashBoxSessionStatus::OPEN
        ]);

        if (!$activeSession) {
            return $this->json([
                'message' => 'Error de Caja',
                'errors' => ['cashBox' => 'No puedes registrar ventas sin una sesión de caja abierta.']
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $sale = new Sale();
        $now = new \DateTime();
        // Asignamos datos automáticos antes de procesar el formulario
        $sale->setUser($user);
        $sale->setCashBox($activeSession->getCashBox());
        $sale->setSaleDate($now);
        $newFolio = $this->generateDailyFolio($now);
        $sale->setFolio($newFolio);
        $sale->setCreatedAt($now);
        $sale->setUpdatedAt($now);
        $sale->setCreatedBy($user->getId());
        $sale->setUpdatedBy($user->getId());

        // 2. Procesar Formulario
        $form = $this->createForm($this->getFormTypeClass(), $sale);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isSubmitted() && $form->isValid()) {

            // 1. Procesar Pagos y sus campos de auditoría
            foreach ($sale->getPayments() as $payment) {
                $payment->setCreatedBy($user->getId()); // Suponiendo que BaseEntity tiene este método
                $payment->setUpdatedBy($user->getId()); // Suponiendo que BaseEntity tiene este método
            }

            // 2. Procesar Propinas, vincularlas a un pago y auditoría
            foreach ($sale->getTips() as $tip) {
                $tip->setCreatedBy($user->getId());
                $tip->setUpdatedBy($user->getId());
                $tip->setTipDate(new \DateTime());

                // Buscamos el pago que financia esta propina (basado en el paymentType)
                $matchingPayment = $this->findMatchingPayment($sale, $tip->getPaymentType());
                if ($matchingPayment) {
                    $tip->setSalePayment($matchingPayment);
                }

                $this->entityManager->persist($tip);
            }

            $this->entityManager->persist($sale);
            $this->entityManager->flush();

            return $this->json([
                'message' => "Venta y pagos registrados correctamente",
                'data' => ['id' => $sale->getId(), 'folio' => $sale->getFolio()]
            ], JsonResponse::HTTP_OK);
        }

        // 3. Captura de errores detallada (Aquí resolvemos el JSON vacío)
        return $this->json([
            'message' => 'Validación fallida',
            'errors' => $this->getFormErrorsAsArray($form)
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    /**
     * Busca el pago dentro de la venta actual que coincida con el tipo de pago de la propina
     */
    private function findMatchingPayment(Sale $sale, PaymentType $type): ?SalePayment
    {
        foreach ($sale->getPayments() as $payment) {
            if ($payment->getPaymentType() === $type) {
                return $payment;
            }
        }
        return null;
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

    private function getFormErrorsAsArray($form): array
    {
        $errors = [];

        // Errores globales (Ej: La validación de suma de pagos en la Entidad)
        foreach ($form->getErrors() as $error) {
            $errors['global'][] = $error->getMessage();
        }

        // Errores en campos individuales
        foreach ($form->all() as $child) {
            if (!$child->isValid()) {
                $childErrors = $this->getFormErrorsAsArray($child);
                if (!empty($childErrors)) {
                    $errors['fields'][$child->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }

    #[Rest\Put('/sale/{id}')]
    public function update(Request $request, Sale $id): JsonResponse
    {
        // Útil para cancelaciones o cambios de estado
        return $this->processForm($request, $id, "Venta actualizada correctamente");
    }

    #[Rest\Delete('/sale/{id}')]
    public function remove(Sale $id): JsonResponse
    {
        return $this->delete($id);
    }

    #[Rest\Get('/sale/{id}')]
    public function get(Sale $id): JsonResponse
    {
        return $this->getDetails($id);
    }
}
