<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\ContextService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

class AuthenticationSuccessListener
{
    public function __construct(
        private readonly ContextService $contextService
    ) {
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        
        // Agregar información del usuario
        $data['user'] = [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles()
        ];

        // Agregar sucursales disponibles para selección
        $data['available_branches'] = $this->contextService->getAvailableBranches();
        
        // Indicar si necesita seleccionar contexto
        $data['requires_context_selection'] = empty($data['available_branches']) ? false : !$this->hasDefaultBranch($data['available_branches']);

        $event->setData($data);
    }

    private function hasDefaultBranch(array $branches): bool
    {
        foreach ($branches as $branch) {
            if ($branch['is_default'] ?? false) {
                return true;
            }
        }
        return false;
    }
}
