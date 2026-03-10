<?php

namespace App\Controller\Api;

use App\Entity\Specialty;
use App\Form\Type\SpecialtyFormType;
use App\Repository\SpecialtyRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SpecialtyController extends BaseController
{
    protected function getEntityClass(): string
    {
        return Specialty::class;
    }

    protected function getFormTypeClass(): string
    {
        return SpecialtyFormType::class;
    }

    protected function getListSelectFields(): array
    {
        return ['u.id', 'u.name', 'u.isActive'];
    }

    protected function getSearchFields(): array
    {
        return ['u.name'];
    }

    #[Rest\Get('/specialty')]
    public function index(Request $request, SpecialtyRepository $repository): JsonResponse
    {
        return $this->list($request, $repository);
    }

    #[Rest\Post('/specialty')]
    public function create(Request $request): JsonResponse
    {
        return $this->processForm($request, new Specialty(), "Especialidad registrada correctamente");
    }

    #[Rest\Put('/specialty/{id}')]
    public function update(Request $request, Specialty $id): JsonResponse
    {
        return $this->processForm($request, $id, "Especialidad actualizada correctamente");
    }

    #[Rest\Delete('/specialty/{id}')]
    public function remove(Specialty $id): JsonResponse
    {
        return $this->delete($id);
    }

    #[Rest\Get('/specialty/{id}')]
    public function get(Specialty $id): JsonResponse
    {
        return $this->getDetails($id);
    }

    #[Rest\Get('/specialty-all')]
    public function all(SpecialtyRepository $repository): JsonResponse
    {
        return $this->json($repository->getAllToSelect());
    }
}
