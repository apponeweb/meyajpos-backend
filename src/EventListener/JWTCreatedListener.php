<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserBranchRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTCreatedListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UserBranchRepository $userBranchRepository
    ) {
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $request = $this->requestStack->getCurrentRequest();

        // Agregar información básica del usuario
        $payload['user_id'] = $user->getId();
        $payload['name'] = $user->getName();

        // Si viene del endpoint select-context, incluir el contexto
        if ($request && $request->attributes->get('_route') === 'api_select_context') {
            $content = json_decode($request->getContent(), true);
            
            if (isset($content['branchId'])) {
                $payload['branch_id'] = (int) $content['branchId'];
            }
            if (isset($content['companyId'])) {
                $payload['company_id'] = (int) $content['companyId'];
            }
        } else {
            // Login inicial: verificar si tiene sucursal por defecto
            $defaultBranch = $this->userBranchRepository->findDefaultBranch($user);
            
            if ($defaultBranch) {
                $branch = $defaultBranch->getBranch();
                $payload['branch_id'] = $branch->getId();
                $payload['company_id'] = $branch->getCompany()?->getId();
            }
        }

        $event->setData($payload);
    }
}
