<?php

namespace App\License\Controller;

use App\Entity\User;
use App\License\Service\LicenseService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[Rest\Route('/license')]
final class LicenseValidateController extends AbstractFOSRestController
{
    public function __construct(
        private readonly LicenseService $licenseService,
        private readonly Security       $security,
    ) {}

    /**
     * Endpoint para que el POS valide la licencia del usuario autenticado.
     */
    #[Rest\Get('/validate')]
    public function validate(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Usuario no autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $license = $this->licenseService->getActiveLicenseForUser($user);

        if (!$license) {
            return $this->json([
                'hasLicense' => false,
                'license'    => null,
            ]);
        }

        return $this->json([
            'hasLicense' => true,
            'license'    => $this->licenseService->getLicenseClaims($user),
        ]);
    }
}
