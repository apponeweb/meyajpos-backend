<?php

namespace App\Controller\Api;

use App\Entity\PaymentType;
use App\Form\Type\PaymentTypeFormType;
use App\Repository\PaymentTypeRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class PaymentTypeController extends BaseController
{
    protected function getEntityClass(): string
    {
        return PaymentType::class;
    }

    protected function getFormTypeClass(): string
    {
        return PaymentTypeFormType::class;
    }

    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.name',
            'u.description',
            'u.isActive',
            'u.isCash',
            'u.referenceRequired',
        ];
    }

    #[Rest\Get('/payment_type')]
    public function index(Request $request, PaymentTypeRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Get('/payment_type/all')]
    #[Rest\View(serializerEnableMaxDepthChecks: true)]
    public function all(PaymentTypeRepository $masterProductRepository)
    {
        return $masterProductRepository->getAllToSelect();
    }

    #[Rest\Post('/payment_type')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new PaymentType(), "Tipo de pago creado correctamente");
    }

    #[Rest\Put('/payment_type/{id}')]
    public function update(Request $request, PaymentType $id): JsonResponse
    {
        return $this->processForm($request, $id, "Tipo de pago actualizado correctamente");
    }

    #[Rest\Delete('/payment_type/{id}')]
    public function remove(PaymentType $id): mixed
    {
        return $this->delete($id);
    }

    #[Rest\Get('/payment_type/{id}')]
    public function get(PaymentType $id): mixed
    {
        return $this->getDetails($id);
    }
}
