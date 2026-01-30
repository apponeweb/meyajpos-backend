<?php

namespace App\Service;

use App\Entity\CashBoxMovement;
use App\Entity\User;
use App\Enum\CashBoxSessionStatus;
use App\Enum\CashMovementConcept;
use App\Enum\CashMovementType;
use App\Repository\CashBoxMovementRepository;
use App\Repository\CashBoxSessionRepository;
use App\Repository\SalePaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CashBoxMovementService
{
    public function __construct(
        private EntityManagerInterface    $entityManager,
        private CashBoxMovementRepository $movementRepo,
        private SalePaymentRepository     $paymentRepo,
        private CashBoxSessionRepository  $sessionRepo,
        private Security                  $security
    )
    {
    }

    public function createMovement(CashBoxMovement $movement): array
    {
        // 1. Obtener usuario autenticado
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [
                'success' => false,
                'error' => 'Usuario no autenticado o no válido.',
                'code' => 401
            ];
        }

        // 2. Obtener sesión activa del usuario
        $session = $this->sessionRepo->findOneBy([
            'user' => $user,
            'status' => CashBoxSessionStatus::OPEN
        ]);

        if (!$session && $movement->getConcept() !== CashMovementConcept::OPEN_CASH_BOX) {
            return [
                'success' => false,
                'error' => 'No tienes una sesión de caja abierta.',
                'code' => 400
            ];
        }

        // 3. Lógica de validación de saldo para Egresos
        if ($movement->getType() === CashMovementType::EXPENSE) {
            $currentBalance = $this->calculateCurrentBalance($session);
            $requestedAmount = (float)$movement->getAmount();

            if ($requestedAmount > $currentBalance) {
                return [
                    'success' => false,
                    'error' => sprintf('Saldo insuficiente. Efectivo real en caja: %s', number_format($currentBalance, 2)),
                    'code' => 400
                ];
            }
        }

        // 4. Asignación de valores automáticos
        $movement->setCashBoxSession($session);
        $movement->setUser($user);

        // Evitamos el error de "property must not be accessed before initialization"
        $movement->setMovementDate(new \DateTime());

        // 5. Persistencia
        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        return [
            'success' => true,
            'movement' => $movement,
            'code' => 201
        ];
    }

    public function calculateCurrentBalance($session): float
    {
        $initialAmount = (float)$session->getInitialAmount();
        $manualMovementsDiff = $this->movementRepo->getTotalOffsetBySession($session);
        $actualCashFromSales = $this->paymentRepo->getTotalCashBySession($session);

        return $initialAmount + $manualMovementsDiff + $actualCashFromSales;
    }
}
