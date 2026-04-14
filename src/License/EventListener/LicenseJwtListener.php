<?php

namespace App\License\EventListener;

use App\Entity\User;
use App\License\Service\LicenseService;
use App\Repository\BarberProfileRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LicenseJwtListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly LicenseService         $licenseService,
        private readonly BarberProfileRepository $barberProfileRepository,
        private readonly \Symfony\Component\HttpFoundation\RequestStack $requestStack,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_jwt_created' => 'onJwtCreated',
        ];
    }

    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();

        $request = $this->requestStack->getCurrentRequest();
        $baseUrl = $request ? $request->getSchemeAndHttpHost() : '';

        // Añadir foto del perfil si existe
        $profile = $this->barberProfileRepository->findOneBy(['user' => $user]);
        $photo = null;
        if ($profile && $profile->getPhotoUrl()) {
            $photo = $profile->getPhotoUrl();
        } elseif ($user->getPhotoUrl()) {
            $photo = $user->getPhotoUrl();
        }

        if ($photo) {
            $payload['photoUrl'] = (str_starts_with($photo, 'http')) ? $photo : $baseUrl . $photo;
        }

        // Añadir claims de licencia si tiene una activa
        $claims = $this->licenseService->getLicenseClaims($user);
        if ($claims !== null) {
            $payload['isActivated'] = isset($claims['activatedAt']) && $claims['activatedAt'] !== null;
            if (isset($claims['companyId'])) {
                $payload['license'] = $claims;
            }
        }

        $event->setData($payload);
    }
}
