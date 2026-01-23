<?php

namespace App\Entity;

use App\Enum\InventoryMovementType;
use App\Repository\InventoryMovementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entidad de encabezado para movimientos de inventario.
 */
#[ORM\Entity(repositoryClass: InventoryMovementRepository::class)]
#[ORM\Table(name: 'tbd_inventory_movement')]
#[ORM\HasLifecycleCallbacks]
class InventoryMovement extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'id', nullable: false)]
    private ?Branch $branch = null;

    /**
     * Tipo de movimiento mapeado como Enum nativo.
     */
    #[ORM\Column(type: Types::SMALLINT, enumType: InventoryMovementType::class)]
    private InventoryMovementType $movementType;

    #[ORM\Column(type: Types::STRING, length: 80, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $movementAt;

    #[ORM\Column(type: Types::STRING, length: 250, nullable: true)]
    private ?string $observations = null;

    public function __construct()
    {
        $this->movementAt = new \DateTime();
        // Valor por defecto opcional
        $this->movementType = InventoryMovementType::ADJUSTMENT_PLUS;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): self
    {
        $this->branch = $branch;
        return $this;
    }

    public function getMovementType(): InventoryMovementType
    {
        return $this->movementType;
    }

    public function setMovementType(InventoryMovementType $movementType): self
    {
        $this->movementType = $movementType;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function getMovementAt(): \DateTimeInterface
    {
        return $this->movementAt;
    }

    public function setMovementAt(\DateTimeInterface $movementAt): self
    {
        $this->movementAt = $movementAt;
        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): self
    {
        $this->observations = $observations;
        return $this;
    }
}
