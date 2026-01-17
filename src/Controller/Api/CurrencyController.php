<?php

namespace App\Controller\Api;

use App\Entity\Currency;
use App\Form\Type\CurrencyFormType;
use App\Repository\CurrencyRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CurrencyController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Currency::class;
    }

    protected function getFormTypeClass(): string
    {
        return CurrencyFormType::class;
    }
    protected function getListSelectFields(): array
    {
        return [
            'u.id',
            'u.name',
            'u.description',
            'u.isActive',
            'u.key',
            'u.symbol',
            'u.decimals'
        ];
    }
    #[Rest\Get('/currency')]
    public function index(Request $request, CurrencyRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/currency')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new Currency(), "Sucursal creada correctamente");
    }

    #[Rest\Put('/currency/{id}')]
    public function update(Request $request, Currency $id): JsonResponse
    {
        return $this->processForm($request, $id, "Sucursal actualizada correctamente");
    }

    #[Rest\Delete('/currency/{id}')]
    public function remove(Currency $id): mixed
    {
        return $this->delete($id);
    }

    #[Rest\Get('/currency/{id}')]
    public function get(Currency $id): mixed
    {
        return $this->getDetails($id);
    }
}
