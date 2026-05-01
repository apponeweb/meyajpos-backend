<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserBranchRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class BranchContextListener
{
    public function __construct(
        private readonly Security             $security,
        private readonly UserBranchRepository $userBranchRepository,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $branchId = $request->headers->get('X-Branch-Id');

        if (!$branchId) {
            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        // Los ADMIN tienen acceso a todas las sucursales
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $request->attributes->set('activeBranchId', (int)$branchId);
            return;
        }

        // Para otros roles, validar que la sucursal esté asignada al usuario
        if ($this->userBranchRepository->userHasAccessToBranch($user->getId(), (int)$branchId)) {
            $request->attributes->set('activeBranchId', (int)$branchId);
        }
    }
}
